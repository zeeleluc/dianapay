<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SolanaCall;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SolanaAutoSell extends Command
{
    protected $signature = 'solana:auto-sell';
    protected $description = 'Automatically sell tokens based on momentum and time';

    protected float $minLiquidity = 1000;  // Minimum liquidity for sell
    protected float $m5Threshold = -25.0;  // Default: Sell if 5-minute price change < -22%
    protected float $h1Threshold = 0.0;    // Confirm M5 sell with H1 trend
    protected int $maxHoldMinutes = 60;    // Sell after 60 minutes if no other conditions

    public function handle()
    {
        $calls = SolanaCall::with('orders')->get()->filter(function ($call) {
            $hasBuy = $call->orders->where('type', 'buy')->count() > 0;
            $hasSell = $call->orders->where('type', 'sell')->count() > 0;
            return $hasBuy && !$hasSell;
        });

        $this->info("Found {$calls->count()} calls eligible for potential sell.");

        $totalBuyOrders = $calls->sum(function ($call) {
            return $call->orders->where('type', 'buy')->count();
        });
        $this->info("Total buy orders across all calls: {$totalBuyOrders}");

        foreach ($calls as $call) {
            try {
                // Check failures first
                $failures = $call->orders->where('type', 'failed')->count();
                if ($failures >= 10) {
                    $this->warn("Deleting SolanaCall ID {$call->id}: {$failures} failed attempts.");
                    $call->delete();
                    continue;
                }

                // Verify exactly one buy order
                $buyOrdersCount = $call->orders->where('type', 'buy')->count();
                $this->info("SolanaCall ID {$call->id}: {$buyOrdersCount} buy orders found.");
                if ($buyOrdersCount !== 1) {
                    $this->warn("Skipping SolanaCall ID {$call->id}: Expected 1 buy order, found {$buyOrdersCount}.");
                    Log::warning("Unexpected buy order count for SolanaCall ID {$call->id}", [
                        'buy_orders' => $buyOrdersCount,
                        'orders' => $call->orders->pluck('type')->toJson(),
                    ]);
                    continue;
                }

                $buyOrder = $call->orders->where('type', 'buy')->first();
                $tokenAddress = $call->token_address;

                if (!$buyOrder || !$buyOrder->amount_foreign) {
                    $this->warn("Skipping SolanaCall ID {$call->id}: no buy order or amount_foreign");
                    continue;
                }

                // Fetch latest data from Birdeye API
                $cacheKey = "token_data_{$tokenAddress}";
                $data = Cache::remember($cacheKey, 30, function () use ($tokenAddress) {
                    $apiKey = config('services.birdeye.api_key');
                    $baseUrl = config('services.birdeye.base_url');
                    $response = Http::withHeaders([
                        'X-API-KEY' => $apiKey,
                        'Accept' => 'application/json',
                    ])->timeout(5)->get("{$baseUrl}/defi/token_overview", [
                        'address' => $tokenAddress,
                    ]);

                    if (!$response->successful() || null === $response->json('data')) {
                        Log::warning("Birdeye API failed for {$tokenAddress}", [
                            'status' => $response->status(),
                            'response' => $response->body(),
                        ]);
                        return null;
                    }

                    return $response->json('data');
                });

                if ($data === null) {
                    $this->warn("Birdeye API failed for {$tokenAddress}, forcing immediate sell to avoid blind holding.");

                    $tokenAmount = $buyOrder->amount_foreign;

                    $process = new Process([
                        'node',
                        base_path('scripts/solana-sell.js'),
                        '--identifier=' . $call->id,
                        '--token=' . $tokenAddress,
                        '--amount=' . $tokenAmount,
                    ]);

                    $process->setTimeout(360);
                    $process->run();
                    $process->wait();

                    if (!$process->isSuccessful()) {
                        $this->error("Forced sell (API fail) failed for SolanaCall ID {$call->id}: " . $process->getErrorOutput());
                    } else {
                        $output = trim($process->getOutput());
                        $this->info("Forced sell completed for SolanaCall ID {$call->id}: {$output}");

                        Log::warning("Forced sell executed due to API failure", [
                            'call_id' => $call->id,
                            'token' => $tokenAddress,
                            'amount_foreign' => $tokenAmount,
                        ]);
                    }

                    continue; // Skip rest of loop after forced sell
                }

                // Extract data from Birdeye response
                $currentPrice = $data['price'] ?? 0;
                $currentLiquidity = $data['liquidity'] ?? 0;
                $priceChangeM5 = $data['priceChange']['m5'] ?? 0;
                $priceChangeH1 = $data['priceChange']['h1'] ?? 0;
                $priceChangeH24 = $data['priceChange']['h24'] ?? 0;

                if (!is_numeric($currentPrice) || $currentPrice <= 0) {
                    $this->warn("Invalid price for token {$tokenAddress}");
                    continue;
                }

                if (!is_numeric($priceChangeM5)) {
                    $this->warn("Invalid M5 price change for token {$tokenAddress}, assuming 0");
                    $priceChangeM5 = 0;
                }

                if ($currentLiquidity < $this->minLiquidity) {
                    $this->warn("Skipping sell for {$tokenAddress}: liquidity \${$currentLiquidity} < \${$this->minLiquidity}");
                    continue;
                }

                // Adjust m5Threshold based on 24-hour volatility
                $volatility = abs($priceChangeH24);
                $m5Threshold = $volatility > 20 ? -10.0 : -5.0; // Looser for high volatility

                // Check hold time
                $holdTime = now()->diffInMinutes($buyOrder->created_at);

                $this->info("Token {$tokenAddress} M5: {$priceChangeM5}%, H1: {$priceChangeH1}%, Volatility: {$volatility}%, Hold Time: {$holdTime} minutes");

                // Sell conditions
                $sellReason = null;
                if ($priceChangeM5 < $m5Threshold && $priceChangeH1 < $this->h1Threshold) {
                    $sellReason = "negative M5 ({$priceChangeM5}%) with weak H1 ({$priceChangeH1}%)";
                } elseif ($holdTime > $this->maxHoldMinutes) {
                    $sellReason = "maximum hold time exceeded ({$holdTime} minutes)";
                }

                if ($sellReason) {
                    $this->info("Triggering sell for SolanaCall ID {$call->id} (M5: {$priceChangeM5}%, Reason: {$sellReason})");

                    $tokenAmount = $buyOrder->amount_foreign;

                    $process = new Process([
                        'node',
                        base_path('scripts/solana-sell.js'),
                        '--identifier=' . $call->id,
                        '--token=' . $tokenAddress,
                        '--amount=' . $tokenAmount,
                    ]);

                    $process->setTimeout(360);
                    $process->run();
                    $process->wait();

                    if (!$process->isSuccessful()) {
                        $this->error("Sell failed for SolanaCall ID {$call->id}: " . $process->getErrorOutput());
                    } else {
                        $output = trim($process->getOutput());
                        $this->info("Sell completed for SolanaCall ID {$call->id}: {$output}");

                        // Log trade outcome for analysis
                        Log::info("Trade outcome", [
                            'call_id' => $call->id,
                            'token' => $tokenAddress,
                            'sell_price' => $currentPrice,
                            'amount_foreign' => $tokenAmount,
                            'reason' => $sellReason,
                            'm5_change' => $priceChangeM5,
                            'h1_change' => $priceChangeH1,
                            'hold_time' => $holdTime,
                        ]);
                    }
                } else {
                    $this->info("Holding token {$tokenAddress}: M5 ({$priceChangeM5}%) still viable");
                }

            } catch (\Exception $e) {
                $this->error("Error processing SolanaCall ID {$call->id}: {$e->getMessage()}");
                Log::error("SolanaAutoSell error: " . $e->getMessage(), ['call_id' => $call->id]);
            }
        }

        $this->info('SolanaAutoSell run completed.');
    }
}
