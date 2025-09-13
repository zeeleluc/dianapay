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

            foreach ($tokens as $token) {
                if ($matchesFound >= 15) break; // max 15 new coins

                $tokenAddress = $token['tokenAddress'] ?? null;
                $chain = $token['chainId'] ?? 'solana';
                if (!$tokenAddress) continue;

                // Fetch token name
                try {
                    $tokenInfo = Http::get("https://api.dexscreener.com/tokens/v1/{$chain}/{$tokenAddress}")->json();
                    $tokenName = (!empty($tokenInfo) && isset($tokenInfo[0]['baseToken']['name']))
                        ? mb_substr($tokenInfo[0]['baseToken']['name'], 0, 255)
                        : 'Unknown Token';
                } catch (Throwable $e) {
                    $tokenName = 'Unknown Token';
                    \Log::warning("Failed to fetch token name for {$tokenAddress}: {$e->getMessage()}");
                }

                if (SolanaCall::where('token_address', $tokenAddress)->exists()) continue;

                // Fetch pair data
                $pairResponse = Http::get("https://api.dexscreener.com/token-pairs/v1/{$chain}/{$tokenAddress}");
                $pairs = $pairResponse->json();
                if (empty($pairs)) continue;

                $pair = $pairs[0];
                $marketCap = $pair['marketCap'] ?? 0;
                $liquidityUsd = $pair['liquidity']['usd'] ?? 0;
                $volume24h = !empty($pair['volume']) ? reset($pair['volume']) : 0;
                $priceChange = !empty($pair['priceChange']) ? reset($pair['priceChange']) : 0;

                // Calculate age_minutes
                $pairCreatedAtMs = $pair['pairCreatedAt'] ?? 0;
                if ($pairCreatedAtMs > 0) {
                    $pairCreatedAt = (int) ($pairCreatedAtMs / 1000);
                    $ageMinutes = max(0, round((time() - $pairCreatedAt) / 60));
                } else {
                    continue; // skip if timestamp missing
                }

                if ($ageMinutes > 15) continue; // only new tokens <15 min

                // Skip low metrics
                if ($marketCap < 9_000 || $liquidityUsd < 10_000) continue;

                // Skip rugged tokens
                if ($priceChange <= -50) {
                    $this->info("Token {$tokenName} ({$tokenAddress}) flagged as RUGGED: priceChange {$priceChange}%");
                    continue;
                }

                // Detect potential pump
                $isPump = ($liquidityUsd > 0 && ($volume24h / $liquidityUsd > 0.5) && $priceChange > -5);
                if (!$isPump) continue;

                // Determine strategy (always in format 123-SEC-SELL)
                $seconds = 10;
                $devSold = $token['devSold'] ?? false;
                if ($devSold) $seconds = 5;
                elseif ($marketCap < 10_000 || $liquidityUsd < 10_000) $seconds = 7;
                elseif ($marketCap >= 20_000 || $liquidityUsd >= 15_000) $seconds = 20;
                elseif ($marketCap >= 50_000 || $liquidityUsd >= 30_000) $seconds = 30;
                elseif ($marketCap >= 100_000 && $liquidityUsd >= 50_000 && !$devSold) $seconds = 60;

                $strategy = $seconds . '-SEC-SELL';

                SlackNotifier::success("Found a memecoin directly via Dexscreener: {$tokenName}");

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

                // --- Trigger node snipe process asynchronously ---
                $buyAmount = $testing ? 0.0001 : 0.003;
                if (!$testing) {
                    if ($marketCap >= 100_000 && $liquidityUsd >= 50_000) $buyAmount = 0.03;
                    elseif ($marketCap >= 50_000 && $liquidityUsd >= 30_000) $buyAmount = 0.02;
                    elseif ($marketCap >= 20_000 && $liquidityUsd >= 15_000) $buyAmount = 0.01;
                    elseif ($marketCap >= 10_000 && $liquidityUsd >= 10_000) $buyAmount = 0.006;
                    elseif ($marketCap < 10_000 || $liquidityUsd < 10_000) $buyAmount = 0.003;
                }

                $process = new Process([
                    'node',
                    base_path('scripts/solana-snipe.js'),
                    '--identifier=' . $call->id,
                    '--token=' . $tokenAddress,
                    '--amount=' . $buyAmount,
                    '--strategy=' . $strategy,
                ]);
                $process->setTimeout(360); // 6 mins
                $process->start();

                $this->info("Started snipe process for SolanaCall ID {$call->id} (PID: " . $process->getPid() . ")");

                $matchesFound++;
            }

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error("Error polling tokens: " . $e->getMessage());
            \Log::error($e);
            SlackNotifier::error("Polling tokens failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
