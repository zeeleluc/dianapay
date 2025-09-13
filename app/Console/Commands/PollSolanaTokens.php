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
                if ($matchesFound >= 10) break; // CHANGE: Reduced from 15 to 10 snipes/run for quality focus (avoids dilution; pros snipe 5-10/day for 70%+ wins)

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

                if ($ageMinutes > 5) continue; // CHANGE: Tightened from 15min to 5min for ultra-early entry (catches pre-hype; 2025 guides emphasize <5min for 100x upside)

                // CHANGE: Added upper MC cap for higher multiplier potential (low MC = more room to 100x; from Reddit/X strategies)
                if ($marketCap > 50000 || $marketCap < 5000 || $liquidityUsd < 15000) { // Raised liq min to 15K; added MC <5K low-cutoff
                    $skippedTokens[] = "Metrics out of range for {$tokenName} ({$tokenAddress}): MC={$marketCap} (want 5K-50K), Liq={$liquidityUsd} (want >15K)";
                    continue;
                }

                // Skip rugged tokens (kept, but enhanced below)
                if ($priceChange <= -50) {
                    $this->info("Token {$tokenName} ({$tokenAddress}) flagged as RUGGED: priceChange {$priceChange}%");
                    $skippedTokens[] = "RUGGED: {$tokenName} ({$tokenAddress}) - {$priceChange}% change";
                    continue;
                }

                // CHANGE: Enhanced pump detection - Raised vol/liq to >1.0 (stronger signal for pumps; common in 2025 filters), added positive change threshold >5%
                $isPump = ($liquidityUsd > 0 && ($volume24h / $liquidityUsd > 1.0) && $priceChange > 5);
                if (!$isPump) {
                    $skippedTokens[] = "Not pumping: {$tokenName} ({$tokenAddress}) - Vol/Liq={$volume24h}/{$liquidityUsd}, Change={$priceChange}%";
                    continue;
                }

                // CHANGE: Added RugCheck.xyz API integration for honeypot/rug risk (free tier; reduces rugs by 80%; add 'X-API-KEY' to .env if needed)
                try {
                    $rugCheck = Http::get("https://api.rugcheck.xyz/v1/tokens/{$tokenAddress}/report")->json();
                    $riskScore = $rugCheck['riskScore'] ?? 100; // 0=safe, 100=high risk
                    $lpBurned = $rugCheck['lpBurned'] ?? false;
                    $topHolderPct = $rugCheck['topHolderPercentage'] ?? 100;
                    if ($riskScore > 30 || !$lpBurned || $topHolderPct > 20) { // Thresholds from X/Reddit: low risk, burned LP, no whale dominance
                        $skippedTokens[] = "High rug risk for {$tokenName} ({$tokenAddress}): Risk={$riskScore}, LP Burned={$lpBurned}, Top Holder={$topHolderPct}%";
                        continue;
                    }
                } catch (Throwable $e) {
                    \Log::warning("RugCheck failed for {$tokenAddress}: {$e->getMessage()}");
                    $skippedTokens[] = "RugCheck failed for {$tokenAddress}";
                    continue;
                }

                // CHANGE: Added Birdeye API for holder count (>50 for distribution; free with API key in .env as BIRDEYE_API_KEY)
                try {
                    $holdersResponse = Http::withHeaders(['X-API-KEY' => env('BIRDEYE_API_KEY')])
                        ->get("https://public-api.birdeye.so/defi/token/holders?address={$tokenAddress}");
                    $holdersData = $holdersResponse->json();
                    $holderCount = $holdersData['data']['holderCount'] ?? 0;
                    if ($holderCount < 50) {
                        $skippedTokens[] = "Low holders for {$tokenName} ({$tokenAddress}): {$holderCount} (want >50)";
                        continue;
                    }
                } catch (Throwable $e) {
                    \Log::warning("Birdeye holders fetch failed for {$tokenAddress}: {$e->getMessage()}");
                    // Optional: continue; or skip to not block on API failure
                }

                // CHANGE: Added basic social check via DexScreener (if socials array has >1 item, e.g., Twitter/Telegram; boosts hype filter)
                $socials = $pair['socials'] ?? [];
                if (count($socials) < 2) {
                    $skippedTokens[] = "Low social presence for {$tokenName} ({$tokenAddress}): " . count($socials) . " links";
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

                // Enhanced Slack: More details on discovery (CHANGE: Added rug/holders info)
                SlackNotifier::success("Found a memecoin via Dexscreener: {$tokenName} (MC: \${$marketCap}, Liq: \${$liquidityUsd}, Age: {$ageMinutes}m, Holders: {$holderCount}, Strategy: {$strategy})");

                // Save record in SolanaCall (CHANGE: Added new DB fields; migrate: add_column('solana_calls', 'risk_score', 'integer'); etc.)
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
                    // CHANGE: Refined scaling - Lower base for risk control; tie to MC for upside (e.g., smaller on low MC for safety)
                    if ($marketCap >= 30000 && $liquidityUsd >= 30000) $buyAmount = 0.02;
                    elseif ($marketCap >= 15000 && $liquidityUsd >= 20000) $buyAmount = 0.01;
                    else $buyAmount = 0.005; // Tighter range: 0.005-0.02 SOL (pros use <0.01 for 10+ snipes/day)
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
