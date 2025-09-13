<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;
use App\Models\SolanaCall;
use App\Helpers\SlackNotifier;

class PollSolanaTokens extends Command
{
    protected $signature = 'poll-solana-tokens
                            {--testing=0 : If 1, use tiny buy amounts for testing}';

    protected $description = 'Poll new Solana tokens, detect potential pumps, and optionally snipes them';

    public function handle(): int
    {
        $testing = (bool) $this->option('testing');
        $this->info('Starting memecoin scan...' . ($testing ? ' [TEST MODE]' : ''));

        try {
            $tokens = Http::get('https://api.dexscreener.com/token-profiles/latest/v1')->json();
            $matchesFound = 0;
            $skippedTokens = []; // For batched warnings
            $failedSnipes = []; // For summary

            foreach ($tokens as $token) {
                if ($matchesFound >= 15) break; // max 15 new coins

                $tokenAddress = $token['tokenAddress'] ?? null;
                $chain = $token['chainId'] ?? 'solana';
                if (!$tokenAddress || $chain !== 'solana') continue; // Fix: Strict Solana filter

                // Fetch token name
                try {
                    $tokenInfo = Http::get("https://api.dexscreener.com/tokens/v1/{$chain}/{$tokenAddress}")->json();
                    $tokenName = (!empty($tokenInfo) && isset($tokenInfo[0]['baseToken']['name']))
                        ? mb_substr($tokenInfo[0]['baseToken']['name'], 0, 255)
                        : 'Unknown Token';
                } catch (Throwable $e) {
                    $tokenName = 'Unknown Token';
                    \Log::warning("Failed to fetch token name for {$tokenAddress}: {$e->getMessage()}");
                    $skippedTokens[] = "Name fetch failed for {$tokenAddress} ({$tokenName}): {$e->getMessage()}";
                }

                if (SolanaCall::where('token_address', $tokenAddress)->exists()) continue;

                // Fetch pair data
                $pairResponse = Http::get("https://api.dexscreener.com/token-pairs/v1/{$chain}/{$tokenAddress}");
                $pairs = $pairResponse->json();
                if (empty($pairs)) {
                    $skippedTokens[] = "No pairs for {$tokenAddress}";
                    continue;
                }

                $pair = $pairs[0];
                $marketCap = $pair['marketCap'] ?? 0;
                $liquidityUsd = $pair['liquidity']['usd'] ?? 0;
                // Fix: Safer access to volume/priceChange (objects, not arrays; use 'h' for hourly)
                $volume24h = $pair['volume']['h'] ?? 0; // Hourly volume for new tokens
                $priceChange = $pair['priceChange']['h'] ?? 0; // Hourly change

                // Calculate age_minutes
                $pairCreatedAtMs = $pair['pairCreatedAt'] ?? 0;
                if ($pairCreatedAtMs > 0) {
                    $pairCreatedAt = (int) ($pairCreatedAtMs / 1000);
                    $ageMinutes = max(0, round((time() - $pairCreatedAt) / 60));
                } else {
                    $skippedTokens[] = "Missing timestamp for {$tokenAddress}";
                    continue; // skip if timestamp missing
                }

                if ($ageMinutes > 15) continue; // only new tokens <15 min

                // Fix: Consistent threshold (10k instead of 9k)
                if ($marketCap < 10_000 || $liquidityUsd < 10_000) {
                    $skippedTokens[] = "Low metrics for {$tokenName} ({$tokenAddress}): MC={$marketCap}, Liq={$liquidityUsd}";
                    continue;
                }

                // Skip rugged tokens
                if ($priceChange <= -50) {
                    $this->info("Token {$tokenName} ({$tokenAddress}) flagged as RUGGED: priceChange {$priceChange}%");
                    $skippedTokens[] = "RUGGED: {$tokenName} ({$tokenAddress}) - {$priceChange}% change";
                    continue;
                }

                // Detect potential pump
                $isPump = ($liquidityUsd > 0 && ($volume24h / $liquidityUsd > 0.5) && $priceChange > -5);
                if (!$isPump) {
                    $skippedTokens[] = "Not pumping: {$tokenName} ({$tokenAddress}) - Vol/Liq={$volume24h}/{$liquidityUsd}, Change={$priceChange}%";
                    continue;
                }

                // Determine strategy (always in format 123-SEC-SELL)
                $seconds = 10;
                $devSold = $token['devSold'] ?? false;
                if ($devSold) $seconds = 5;
                elseif ($marketCap < 10_000 || $liquidityUsd < 10_000) $seconds = 7;
                elseif ($marketCap >= 20_000 || $liquidityUsd >= 15_000) $seconds = 20;
                elseif ($marketCap >= 50_000 || $liquidityUsd >= 30_000) $seconds = 30;
                elseif ($marketCap >= 100_000 && $liquidityUsd >= 50_000 && !$devSold) $seconds = 60;

                $strategy = $seconds . '-SEC-SELL';
                $strategy = 'TAKE-PROFITS-OR-SMALL-LOSE';

                // Enhanced Slack: More details on discovery
                SlackNotifier::success("Found a memecoin directly via Dexscreener: {$tokenName} (MC: \${$marketCap}, Liq: \${$liquidityUsd}, Age: {$ageMinutes}m, Strategy: {$strategy})");

                // Save record in SolanaCall
                $call = SolanaCall::create([
                    'token_name' => $tokenName,
                    'token_address' => $tokenAddress,
                    'age_minutes' => $ageMinutes,
                    'market_cap' => $marketCap,
                    'volume_24h' => $volume24h,
                    'liquidity_pool' => $liquidityUsd,
                    'strategy' => $strategy,
                    'dev_sold' => $devSold,
                    'dex_paid_status' => $token['dexPaidStatus'] ?? false,
                ]);

                $this->info("Saved SolanaCall ID: {$call->id} - Token: {$tokenName} ({$tokenAddress}) - Strategy: {$strategy}");

                // --- Trigger node snipe process synchronously for logging ---
                $buyAmount = $testing ? 0.0001 : 0.003;
                if (!$testing) {
                    if ($marketCap >= 100_000 && $liquidityUsd >= 50_000) $buyAmount = 0.03;
                    elseif ($marketCap >= 50_000 && $liquidityUsd >= 30_000) $buyAmount = 0.02;
                    elseif ($marketCap >= 20_000 && $liquidityUsd >= 15_000) $buyAmount = 0.01;
                    elseif ($marketCap >= 10_000 && $liquidityUsd >= 10_000) $buyAmount = 0.006;
                    elseif ($marketCap < 10_000 || $liquidityUsd < 10_000) $buyAmount = 0.003;
                }

                // Slack: Launch notification
                SlackNotifier::info("Launching snipe for SolanaCall #{$call->id}: {$tokenName} ({$buyAmount} SOL, {$strategy})");

                $process = new Process([
                    'node',
                    base_path('scripts/solana-snipe.js'),
                    '--identifier=' . $call->id,
                    '--token=' . $tokenAddress,
                    '--amount=' . $buyAmount,
                    '--strategy=' . $strategy,
                    // Optional: Add --verbose for detailed sniper logs
                ]);
                $process->setTimeout(360); // 6 mins
                $process->start();

                $this->info("Started snipe process for SolanaCall ID {$call->id} (PID: " . $process->getPid() . ")");

                // Wait for completion and capture output for logging
                $process->wait();
                $exitCode = $process->getExitCode();
                $output = trim($process->getOutput());
                $errorOutput = trim($process->getErrorOutput());

                if ($exitCode === 0 && !empty($output)) {
                    // Success: Log output (includes tx sigs, balances from sniper)
                    SlackNotifier::success("✅ Snipe completed for #{$call->id} ({$tokenName}): Exit 0\n```{$output}```");
                    $this->info("Snipe success for #{$call->id}");
                } elseif ($exitCode !== 0 || !empty($errorOutput)) {
                    // Failure: Log errors
                    $errorMsg = $errorOutput ?: $output ?: 'Unknown error (exit ' . $exitCode . ')';
                    SlackNotifier::error("❌ Snipe failed for #{$call->id} ({$tokenName}): {$errorMsg}");
                    $this->error("Snipe failed for #{$call->id}: {$errorMsg}");
                    $failedSnipes[] = "#{$call->id}: {$errorMsg}";
                } else {
                    SlackNotifier::info("Snipe completed for #{$call->id} ({$tokenName}) (no output)");
                }

                $matchesFound++;
            }

//            // Slack: Batched skips and summary
//            if (!empty($skippedTokens)) {
//                $skipsMsg = "Skipped " . count($skippedTokens) . " tokens: " . implode('; ', array_slice($skippedTokens, 0, 5)) . (count($skippedTokens) > 5 ? '...' : '');
//                SlackNotifier::warning($skipsMsg);
//            }
//            if (!empty($failedSnipes)) {
//                $failsMsg = "Failed snipes: " . implode('; ', array_slice($failedSnipes, 0, 3)) . (count($failedSnipes) > 3 ? '...' : '');
//                SlackNotifier::error($failsMsg);
//            }

            $summary = "Poll complete: Processed {$matchesFound} tokens.";
            $this->info($summary);

            return self::SUCCESS;

        } catch (Throwable $e) {
            $errorMsg = "Error polling tokens: " . $e->getMessage();
            $this->error($errorMsg);
            \Log::error($e);
            SlackNotifier::error($errorMsg);
            return self::FAILURE;
        }
    }
}
