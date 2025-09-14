<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SolanaCall;
use App\Models\SolanaCallOrder;
use App\Services\SolanaContractScanner;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;
use App\Helpers\SlackNotifier;

class BuyNewSolanaTokens extends Command
{
    protected $signature = 'solana:buy-new-solana-tokens';
    protected $description = 'Poll new trending/boosted Solana tokens and buy them using solana-buy.js';

    public function handle(): int
    {
        try {
            $boostedPairs = Http::get('https://api.dexscreener.com/token-boosts/latest/v1')->json() ?? [];
            $topBoostedPairs = Http::get('https://api.dexscreener.com/token-boosts/top/v1')->json() ?? [];

            $allTokens = [];
            foreach (array_merge($boostedPairs, $topBoostedPairs) as $b) {
                if (!isset($b['tokenAddress']) || ($b['chainId'] ?? '') !== 'solana') continue;
                $allTokens[] = [
                    'tokenAddress' => $b['tokenAddress'],
                    'chainId'      => $b['chainId'] ?? 'solana',
                    'boosted'      => true,
                    'extra'        => $b,
                ];
            }

            foreach ($allTokens as &$token) {
                $pair = $this->getTokenPair($token['tokenAddress'], $token['chainId']);
                $token['tokenName'] = substr($pair['baseToken']['name'] ?? 'Unknown Token', 0, 100);
                $token['ticker'] = $pair['baseToken']['symbol'] ?? null;
                $token['pair'] = $pair; // store for reuse
            }

            $matchesFound = 0;

            foreach ($allTokens as $token) {
                if ($matchesFound >= 10) break;

                $tokenAddress = $token['tokenAddress'];
                $chain = $token['chainId'];
                $tokenName = $token['tokenName'];
                $isBoosted = $token['boosted'] ?? false;
                $pair = $token['pair'] ?? [];

                if (!$tokenAddress || $chain !== 'solana') continue;
                if ($this->shouldSkipToken($tokenAddress)) continue;

                // 🔍 Run scanner with stored pair
                $scanner = new SolanaContractScanner($tokenAddress, $chain, true);
                $scanner->setBoosted($isBoosted);
                if (!$scanner->canTrade($pair)) continue;

                // --- Calculate age and basic metrics from stored pair ---
                $pairCreatedAtMs = $pair['pairCreatedAt'] ?? 0;
                $nowMs = round(microtime(true) * 1000);
                $ageSeconds = ($nowMs - $pairCreatedAtMs) / 1000;

                if ($ageSeconds > 120) continue; // skip tokens older than 2 minutes

                $liquidityUsd = $pair['liquidity']['usd'] ?? 0;
                $volume24h = $pair['volume']['h24'] ?? 0;

                // Fetch latest market cap once
                $res = Http::timeout(5)->get("https://api.dexscreener.com/latest/dex/tokens/{$tokenAddress}");
                if (!$res->successful() || empty($res->json('pairs'))) {
                    $this->warn("Failed to fetch market cap for token {$tokenAddress}");
                    continue;
                }
                $currentMarketCap = $res->json('pairs.0.marketCap');

                // --- Save call record ---
                $call = SolanaCall::create([
                    'token_name'    => substr($tokenName, 0, 100),
                    'token_address' => $tokenAddress,
                    'age_minutes'   => floor($ageSeconds / 60),
                    'market_cap'    => $currentMarketCap,
                    'volume_24h'    => $volume24h,
                    'liquidity_pool'=> $liquidityUsd,
                    'strategy'      => 'NEW-SNIPE',
                ]);

                $this->info("Saved SolanaCall ID: {$call->id} - Token: {$tokenName}");

                // --- Determine buy amount ---
                $buyAmount = 0.001;
                SlackNotifier::info("Launching buy for SolanaCall #{$call->id}: {$tokenName} ({$buyAmount} SOL)");

                // --- Launch Node.js buy process ---
                $process = new Process([
                    'node',
                    base_path('scripts/solana-buy.js'),
                    '--identifier=' . $call->id,
                    '--token=' . $tokenAddress,
                    '--amount=' . $buyAmount,
                ]);
                $process->setTimeout(360);
                $process->run();

                $exitCode = $process->getExitCode();
                $output = trim($process->getOutput());
                $errorOutput = trim($process->getErrorOutput());

                if ($exitCode === 0 && !empty($output)) {
                    SlackNotifier::success("✅ Buy completed for #{$call->id} ({$tokenName}): Exit 0\n```{$output}```");
                    $this->info("Buy success for #{$call->id}");
                } else {
                    $errorMsg = $errorOutput ?: $output ?: 'Unknown error (exit ' . $exitCode . ')';
                    SlackNotifier::error("❌ Buy failed for #{$call->id} ({$tokenName}): {$errorMsg}");
                    $this->error("Buy failed for #{$call->id}: {$errorMsg}");
                }

                $matchesFound++;
            }

            $this->info("Poll complete: Processed {$matchesFound} new tokens.");
            return self::SUCCESS;

        } catch (Throwable $e) {
            $errorMsg = "Error polling new tokens: " . $e->getMessage();
            $this->error($errorMsg);
            \Log::error($e);
            SlackNotifier::error($errorMsg);
            return self::FAILURE;
        }
    }

    private function getTokenPair(string $tokenAddress, string $chain): array
    {
        $response = Http::get("https://api.dexscreener.com/token-pairs/v1/{$chain}/{$tokenAddress}");
        if ($response->failed()) return [];
        return $response->json()[0] ?? [];
    }

    private function shouldSkipToken(string $tokenAddress): bool
    {
        $call = SolanaCall::where('token_address', $tokenAddress)
            ->latest('id')
            ->with('orders')
            ->first();

        if (!$call) return false;

        $orders = $call->orders;
        if ($orders->isEmpty()) return true;

        $hasBuy = $orders->where('type', 'buy')->count() > 0;
        $hasSell = $orders->where('type', 'sell')->count() > 0;

        if ($hasBuy && !$hasSell) return true;
        if ($hasBuy && $hasSell) {
            $lastSell = SolanaCallOrder::whereHas('solanaCall', fn($q) => $q->where('token_address', $tokenAddress))
                ->where('type', 'sell')
                ->latest('created_at')
                ->first();

            return $lastSell && $lastSell->created_at->gt(Carbon::now()->subHours(2));
        }

        return false;
    }
}
