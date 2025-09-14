<?php

namespace App\Console\Commands;

use App\Helpers\SlackNotifier;
use App\Helpers\SolanaTokenData;
use App\Models\SolanaCall;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SolanaAutoSell extends Command
{
    protected $signature = 'solana:auto-sell';
    protected $description = 'Automatically sell tokens based on 5-minute price drop or time';

    protected float $minLiquidity = 1000;  // Minimum liquidity for sell
    protected float $m5Threshold = -5.0;   // Sell if 5-minute price change < -5%
    protected int $maxHoldMinutes = 120;   // Sell after 120 minutes

    public function handle()
    {
        Log::info('[AutoSell Command] Starting solana:auto-sell command', [
            'timestamp' => now()->toDateTimeString(),
        ]);

        $calls = SolanaCall::with('orders')->get()->filter(function ($call) {
            $hasBuy = $call->orders->where('type', 'buy')->count() > 0;
            $hasSell = $call->orders->where('type', 'sell')->count() > 0;
            return $hasBuy && !$hasSell;
        });

        $this->info("Found {$calls->count()} calls eligible for potential sell.");
        Log::info('[AutoSell Command] Found eligible calls', [
            'count' => $calls->count(),
            'call_ids' => $calls->pluck('id')->toArray(),
        ]);

        $totalBuyOrders = $calls->sum(function ($call) {
            return $call->orders->where('type', 'buy')->count();
        });
        $this->info("Total buy orders across all calls: {$totalBuyOrders}");
        Log::info('[AutoSell Command] Total buy orders', [
            'total' => $totalBuyOrders,
        ]);

        $tokenDataHelper = new SolanaTokenData();

        foreach ($calls as $call) {
            try {
                // Check failures first
                $failures = $call->orders->where('type', 'failed')->count();
                if ($failures >= 10) {
                    $this->warn("Deleting SolanaCall ID {$call->id}: {$failures} failed attempts.");
                    Log::warning('[AutoSell Command] Deleting call due to excessive failures', [
                        'call_id' => $call->id,
                        'failures' => $failures,
                    ]);
                    $call->delete();
                    continue;
                }

                // Verify exactly one buy order
                $buyOrdersCount = $call->orders->where('type', 'buy')->count();
                $this->info("SolanaCall ID {$call->id}: {$buyOrdersCount} buy orders found.");
                Log::info('[AutoSell Command] Checking buy orders for call', [
                    'call_id' => $call->id,
                    'buy_orders' => $buyOrdersCount,
                ]);

                if ($buyOrdersCount !== 1) {
                    $this->warn("Skipping SolanaCall ID {$call->id}: Expected 1 buy order, found {$buyOrdersCount}.");
                    Log::warning('[AutoSell Command] Skipping call due to unexpected buy order count', [
                        'call_id' => $call->id,
                        'buy_orders' => $buyOrdersCount,
                        'orders' => $call->orders->pluck('type')->toJson(),
                    ]);
                    continue;
                }

                $buyOrder = $call->orders->where('type', 'buy')->first();
                $tokenAddress = $call->token_address;

                if (!$buyOrder || !$buyOrder->amount_foreign) {
                    $this->warn("Skipping SolanaCall ID {$call->id}: no buy order or amount_foreign");
                    Log::warning('[AutoSell Command] Skipping call due to missing buy order or amount_foreign', [
                        'call_id' => $call->id,
                        'buy_order_exists' => !empty($buyOrder),
                        'amount_foreign' => $buyOrder ? $buyOrder->amount_foreign : null,
                    ]);
                    continue;
                }

                // Validate arguments for solana-sell.js
                if (!is_numeric($call->id) || empty($tokenAddress) || !is_numeric($buyOrder->amount_foreign)) {
                    $this->error("Invalid arguments for SolanaCall ID {$call->id}: id={$call->id}, token={$tokenAddress}, amount={$buyOrder->amount_foreign}");
                    Log::error('[AutoSell Command] Invalid arguments for sell', [
                        'call_id' => $call->id,
                        'token' => $tokenAddress,
                        'amount_foreign' => $buyOrder->amount_foreign,
                    ]);
                    SlackNotifier::error("Invalid arguments for SolanaCall #{$call->id} ({$tokenAddress}): id={$call->id}, amount={$buyOrder->amount_foreign}");
                    continue;
                }

                // Fetch latest data from QuickNode
                Log::info('[AutoSell Command] Fetching token data', [
                    'call_id' => $call->id,
                    'token' => $tokenAddress,
                ]);
                $data = $tokenDataHelper->getTokenData($tokenAddress);

                if ($data === null) {
                    SlackNotifier::error("QuickNode API failed or token not indexed for {$tokenAddress}.");
                    $this->warn("QuickNode API failed for {$tokenAddress}, forcing immediate sell to avoid blind holding.");
                    Log::warning('[AutoSell Command] Forcing sell due to API failure', [
                        'call_id' => $call->id,
                        'token' => $tokenAddress,
                    ]);

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
                        Log::error('[AutoSell Command] Forced sell failed', [
                            'call_id' => $call->id,
                            'token' => $tokenAddress,
                            'error' => $process->getErrorOutput(),
                        ]);
                    } else {
                        $output = trim($process->getOutput());
                        $this->info("Forced sell completed for SolanaCall ID {$call->id}: {$output}");
                        Log::info('[AutoSell Command] Forced sell completed', [
                            'call_id' => $call->id,
                            'token' => $tokenAddress,
                            'amount_foreign' => $tokenAmount,
                            'output' => $output,
                        ]);
                    }

                    continue;
                }

                // Extract data from QuickNode response
                $currentPrice = $data['price'] ?? 0;
                $currentLiquidity = $data['liquidity']['usd'] ?? 0;
                $priceChangeM5 = $data['priceChange']['m5'] ?? 0;
                $priceChangeH1 = $data['priceChange']['h1'] ?? 0;
                $priceChangeH24 = $data['priceChange']['h24'] ?? 0;

                Log::info('[AutoSell Command] Fetched token data', [
                    'call_id' => $call->id,
                    'token' => $tokenAddress,
                    'price' => $currentPrice,
                    'liquidity_usd' => $currentLiquidity,
                    'priceChange' => [
                        'm5' => $priceChangeM5,
                        'h1' => $priceChangeH1,
                        'h24' => $priceChangeH24,
                    ],
                ]);

                if (!is_numeric($currentPrice) || $currentPrice <= 0) {
                    $this->warn("Invalid price for token {$tokenAddress}");
                    Log::warning('[AutoSell Command] Skipping due to invalid price', [
                        'call_id' => $call->id,
                        'token' => $tokenAddress,
                        'price' => $currentPrice,
                    ]);
                    continue;
                }

                if (!is_numeric($priceChangeM5)) {
                    $this->warn("Invalid M5 price change for token {$tokenAddress}, assuming 0");
                    Log::warning('[AutoSell Command] Invalid M5 price change, assuming 0', [
                        'call_id' => $call->id,
                        'token' => $tokenAddress,
                        'priceChangeM5' => $priceChangeM5,
                    ]);
                    $priceChangeM5 = 0;
                }

                if ($currentLiquidity < $this->minLiquidity) {
                    $this->warn("Skipping sell for {$tokenAddress}: liquidity \${$currentLiquidity} < \${$this->minLiquidity}");
                    Log::warning('[AutoSell Command] Skipping due to low liquidity', [
                        'call_id' => $call->id,
                        'token' => $tokenAddress,
                        'liquidity_usd' => $currentLiquidity,
                        'min_liquidity' => $this->minLiquidity,
                    ]);
                    continue;
                }

                // Check hold time (handle negative values)
                $holdTime = max(0, now()->diffInMinutes($buyOrder->created_at));

                $this->info("Token {$tokenAddress} M5: {$priceChangeM5}%, H1: {$priceChangeH1}%, Volatility: {$priceChangeH24}%, Hold Time: {$holdTime} minutes");
                Log::info('[AutoSell Command] Evaluating sell conditions', [
                    'call_id' => $call->id,
                    'token' => $tokenAddress,
                    'm5_change' => $priceChangeM5,
                    'h1_change' => $priceChangeH1,
                    'h24_change' => $priceChangeH24,
                    'm5_threshold' => $this->m5Threshold,
                    'hold_time' => $holdTime,
                    'max_hold_minutes' => $this->maxHoldMinutes,
                    'current_price' => $currentPrice,
                    'created_at' => $buyOrder->created_at->toDateTimeString(),
                ]);

                // Sell conditions
                $sellReason = null;
                if ($priceChangeM5 < $this->m5Threshold) {
                    $sellReason = "negative M5 ({$priceChangeM5}% < {$this->m5Threshold}%)";
                } elseif ($holdTime > $this->maxHoldMinutes) {
                    $sellReason = "maximum hold time exceeded ({$holdTime} minutes)";
                }

                if ($sellReason) {
                    $this->info("Triggering sell for SolanaCall ID {$call->id} (M5: {$priceChangeM5}%, Reason: {$sellReason})");
                    Log::info('[AutoSell Command] Triggering sell', [
                        'call_id' => $call->id,
                        'token' => $tokenAddress,
                        'reason' => $sellReason,
                        'command' => ['node', base_path('scripts/solana-sell.js'), "--identifier={$call->id}", "--token={$tokenAddress}", "--amount={$buyOrder->amount_foreign}"],
                    ]);

                    $tokenAmount = $buyOrder->amount_foreign;

                    $process = new Process([
                        'node',
                        base_path('scripts/solana-sell.js'),
                        "--identifier={$call->id}",
                        "--token={$tokenAddress}",
                        "--amount={$tokenAmount}",
                    ]);

                    $process->setTimeout(360);
                    $process->run();
                    $process->wait();

                    if (!$process->isSuccessful()) {
                        $this->error("Sell failed for SolanaCall ID {$call->id}: " . $process->getErrorOutput());
                        Log::error('[AutoSell Command] Sell failed', [
                            'call_id' => $call->id,
                            'token' => $tokenAddress,
                            'error' => $process->getErrorOutput(),
                            'command' => ['node', base_path('scripts/solana-sell.js'), "--identifier={$call->id}", "--token={$tokenAddress}", "--amount={$tokenAmount}"],
                        ]);
                        SlackNotifier::error("Sell failed for SolanaCall #{$call->id} ({$tokenAddress}): " . $process->getErrorOutput());
                    } else {
                        $output = trim($process->getOutput());
                        $this->info("Sell completed for SolanaCall ID {$call->id}: {$output}");
                        Log::info('[AutoSell Command] Sell completed', [
                            'call_id' => $call->id,
                            'token' => $tokenAddress,
                            'sell_price' => $currentPrice,
                            'amount_foreign' => $tokenAmount,
                            'reason' => $sellReason,
                            'm5_change' => $priceChangeM5,
                            'h1_change' => $priceChangeH1,
                            'hold_time' => $holdTime,
                        ]);
                        SlackNotifier::success("Sell completed for SolanaCall #{$call->id} ({$tokenAddress}): {$output}");
                    }
                } else {
                    $this->info("Holding token {$tokenAddress}: M5 ({$priceChangeM5}% >= {$this->m5Threshold}%)");
                    Log::info('[AutoSell Command] Holding token', [
                        'call_id' => $call->id,
                        'token' => $tokenAddress,
                        'm5_change' => $priceChangeM5,
                        'h1_change' => $priceChangeH1,
                        'hold_time' => $holdTime,
                        'current_price' => $currentPrice,
                        'created_at' => $buyOrder->created_at->toDateTimeString(),
                    ]);
                }

            } catch (\Exception $e) {
                $this->error("Error processing SolanaCall ID {$call->id}: {$e->getMessage()}");
                Log::error('[AutoSell Command] Error processing call', [
                    'call_id' => $call->id,
                    'token' => $tokenAddress,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                SlackNotifier::error("Error processing SolanaCall #{$call->id} ({$tokenAddress}): {$e->getMessage()}");
            }
        }

        $this->info('SolanaAutoSell run completed.');
        Log::info('[AutoSell Command] Command completed', [
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
