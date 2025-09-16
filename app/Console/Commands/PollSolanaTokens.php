<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SolanaBlacklistContract;
use App\Models\SolanaCallOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            $allTokens = [];

            // --- 1. Fetch boosted tokens (trending) ---
            $boostedLatest = Http::get('https://api.dexscreener.com/token-boosts/latest/v1')->json() ?? [];
            $boostedTop = Http::get('https://api.dexscreener.com/token-boosts/top/v1')->json() ?? [];
            $boostedTokens = array_merge($boostedLatest, $boostedTop);

            foreach ($boostedTokens as $token) {
                if (($token['chainId'] ?? '') !== 'solana') continue;
                $allTokens[$token['tokenAddress']] = array_merge($token, ['boosted' => true]);
            }

            // --- 2. Fetch recent pairs for early-mover detection ---
            $recentPairs = Http::get('https://api.dexscreener.com/latest/dex/search?q=SOL/USDC')->json() ?? [];
            foreach ($recentPairs as $pair) {
                $addr = $pair['baseToken']['address'] ?? null;
                if (!$addr || isset($allTokens[$addr])) continue;

                $pairCreatedAtMs = $pair['pairCreatedAt'] ?? 0;
                $pairCreatedAt = $pairCreatedAtMs > 0 ? (int)($pairCreatedAtMs / 1000) : time();
                $ageMinutes = max(0, round((time() - $pairCreatedAt) / 60));

                $volume24h = $pair['volume']['h24'] ?? 0;
                $priceChangeM5 = $pair['priceChange']['m5'] ?? 0;

                // --- Early mover filters ---
                if ($ageMinutes > 60) continue;           // young pairs only
                if ($volume24h < 5000) continue;          // minimal 24h volume
                if ($priceChangeM5 < 1) continue;         // minimal short-term gain

                $allTokens[$addr] = array_merge($pair, ['boosted' => false]);
            }

            $matchesFound = 0;

            // --- 3. Process all tokens ---
            foreach ($allTokens as $token) {
                $tokenAddress = $token['tokenAddress'] ?? $token['baseToken']['address'] ?? null;
                if (!$tokenAddress || SolanaBlacklistContract::isBlacklisted($tokenAddress)) continue;

                $scanner = new SolanaContractScanner($tokenAddress, 'solana');
                $scanner->setBoosted($token['boosted'] ?? false);

                if (!$scanner->canTrade()) continue;

                $data = $scanner->getTokenData();
                $tokenName = substr($token['name'] ?? $token['baseToken']['name'] ?? 'Unknown', 0, 100);

                $call = SolanaCall::create([
                    'token_name' => $tokenName,
                    'token_address' => $tokenAddress,
                    'market_cap' => $data['marketCap'] ?? 0,
                    'liquidity_pool' => $data['liquidity']['usd'] ?? 0,
                    'strategy' => $token['boosted'] ? 'TRENDING-TRADE' : 'EARLY-MOVER',
                    'reason_buy' => $scanner->getBuyReason(),
                ]);

                // --- Launch buy script ---
                $process = new Process([
                    'node',
                    base_path('scripts/solana-buy.js'),
                    '--identifier=' . $call->id,
                    '--token=' . $tokenAddress,
                    '--amount=0.01',
                ]);
                $process->setTimeout(360);
                $process->run();
                $process->wait();

                $exitCode = $process->getExitCode();
                $output = trim($process->getOutput());
                $errorOutput = trim($process->getErrorOutput());

                if ($exitCode === 0 && !empty($output)) {
                    SlackNotifier::success("✅ Buy completed for #{$call->id} ({$tokenName})\n```{$output}```");
                } elseif ($exitCode !== 0 || !empty($errorOutput)) {
                    $errorMsg = $errorOutput ?: $output ?: 'Unknown error';
                    SlackNotifier::error("❌ Buy failed for #{$call->id} ({$tokenName}): {$errorMsg}");
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
        if (SolanaBlacklistContract::isBlacklisted($tokenAddress)) {
            Log::info("Skipping blacklisted token: {$tokenAddress}");
            return true;
        }

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
