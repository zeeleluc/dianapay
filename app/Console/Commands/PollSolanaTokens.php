<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\SolanaTokenData;
use App\Models\SolanaCallOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;
use App\Models\SolanaCall;
use App\Helpers\SlackNotifier;
use App\Services\SolanaContractScanner;

class PollSolanaTokens extends Command
{
    protected $signature = 'solana:poll-solana-tokens';
    protected $description = 'Poll trending + boosted Solana tokens, detect pump potential, and buy them';

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
                $info = $this->getTokenInfo($token['tokenAddress'], $token['chainId']);
                $token['tokenName'] = substr($info['name'] ?? 'Unknown Token', 0, 100);
                $token['ticker'] = $info['symbol'] ?? null;
            }

            $matchesFound = 0;
            $skippedTokens = [];

            foreach ($allTokens as $token) {
                if ($matchesFound >= 10) break;

                $tokenAddress = $token['tokenAddress'] ?? null;
                $chain = $token['chainId'] ?? 'solana';
                $tokenName = $token['tokenName'] ?? 'Unknown Token';
                $isBoosted = $token['boosted'] ?? false;

                if (!$tokenAddress || $chain !== 'solana') continue;
                if ($this->shouldSkipToken($tokenAddress)) continue;

                // ✅ Fetch token-pair data once
                $pairResponse = Http::get("https://api.dexscreener.com/token-pairs/v1/{$chain}/{$tokenAddress}")->json();
                $pair = is_array($pairResponse) && !empty($pairResponse[0]) ? $pairResponse[0] : [];

                // 🔍 Run scanner with fetched pair
                $scanner = new SolanaContractScanner($tokenAddress, $chain);
                $scanner->setBoosted($isBoosted);
                if (!$scanner->canTrade($pair)) {
                    $skippedTokens[] = "Scanner rejected {$tokenName} ({$tokenAddress})";
                    continue;
                }

                $marketCap = $pair['marketCap'] ?? 0;
                $liquidityUsd = $pair['liquidity']['usd'] ?? 0;
                $volume24h = $pair['volume']['h24'] ?? 0;
                $pairCreatedAtMs = $pair['pairCreatedAt'] ?? 0;
                $pairCreatedAt = $pairCreatedAtMs > 0 ? (int)($pairCreatedAtMs / 1000) : time();
                $ageMinutes = max(0, round((time() - $pairCreatedAt) / 60));
                $devSold = $token['devSold'] ?? false;

                $boostLabel = $isBoosted ? ' [BOOSTED]' : '';
                SlackNotifier::success("Found trending{$boostLabel} Solana token: {$tokenName} (MC: \${$marketCap}, Liq: \${$liquidityUsd}, Age: {$ageMinutes}m)");

                $tokenDataHelper = new SolanaTokenData();
                $data = $tokenDataHelper->getTokenData($tokenAddress);

                // Save call in DB
                $call = SolanaCall::create([
                    'token_name' => substr($tokenName, 0, 100),
                    'token_address' => $tokenAddress,
                    'age_minutes' => $ageMinutes,
                    'market_cap' => $data['marketCap'],
                    'volume_24h' => $volume24h,
                    'liquidity_pool' => $liquidityUsd,
                    'strategy' => 'TRENDING-TRADE',
                    'dev_sold' => $devSold,
                    'dex_paid_status' => $token['dexPaidStatus'] ?? false,
                ]);

                $this->info("Saved SolanaCall ID: {$call->id} - Token: {$tokenName} ({$tokenAddress}){$boostLabel}");

                // --- Node buy process ---
                $buyAmount = 0.005;
                SlackNotifier::info("Launching buy for SolanaCall #{$call->id}: {$tokenName} ({$buyAmount} SOL){$boostLabel}");

                $process = new Process([
                    'node',
                    base_path('scripts/solana-buy.js'),
                    '--identifier=' . $call->id,
                    '--token=' . $tokenAddress,
                    '--amount=' . $buyAmount,
                ]);
                $process->setTimeout(360);
                $process->run();
                $process->wait();

                $exitCode = $process->getExitCode();
                $output = trim($process->getOutput());
                $errorOutput = trim($process->getErrorOutput());

                if ($exitCode === 0 && !empty($output)) {
                    SlackNotifier::success("✅ Buy completed for #{$call->id} ({$tokenName}): Exit 0\n```{$output}```");
                    $this->info("Buy success for #{$call->id}");
                } elseif ($exitCode !== 0 || !empty($errorOutput)) {
                    $errorMsg = $errorOutput ?: $output ?: 'Unknown error (exit ' . $exitCode . ')';
                    SlackNotifier::error("❌ Buy failed for #{$call->id} ({$tokenName}): {$errorMsg}");
                    $this->error("Buy failed for #{$call->id}: {$errorMsg}");
                } else {
                    SlackNotifier::info("Buy completed for #{$call->id} ({$tokenName}) (no output)");
                }

                $matchesFound++;
            }

            $this->info("Poll complete: Processed {$matchesFound} tokens.");
            return self::SUCCESS;

        } catch (Throwable $e) {
            $errorMsg = "Error polling tokens: " . $e->getMessage();
            $this->error($errorMsg);
            \Log::error($e);
            SlackNotifier::error($errorMsg);
            return self::FAILURE;
        }
    }

    private function getTokenInfo(string $tokenAddress, string $chain): array
    {
        $response = Http::get("https://api.dexscreener.com/token-pairs/v1/{$chain}/{$tokenAddress}");
        if ($response->failed()) {
            \Log::warning("Failed to fetch token info for {$tokenAddress}");
            return [];
        }

        $data = $response->json();
        $pair = $data[0] ?? [];

        return [
            'name' => $pair['baseToken']['name'] ?? null,
            'symbol' => $pair['baseToken']['symbol'] ?? null,
        ];
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
            $lastSell = SolanaCallOrder::whereHas('solanaCall', function ($q) use ($tokenAddress) {
                $q->where('token_address', $tokenAddress);
            })->where('type', 'sell')->latest('created_at')->first();

            if ($lastSell && $lastSell->created_at->gt(Carbon::now()->subMinutes(15))) return true;
        }

        return false;
    }
}
