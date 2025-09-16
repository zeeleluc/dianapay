<?php

namespace App\Console\Commands;

use App\Helpers\SlackNotifier;
use App\Helpers\SolanaTokenData;
use App\Models\SolanaCall;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SolanaAutoSell extends Command
{
    protected $signature = 'solana:auto-sell';
    protected $description = 'Automatically sell tokens based on profit, 5-minute price drop, or time';

    protected float $minLiquidity = 1000;  // Minimum liquidity for sell
    protected float $m5Threshold = -1.0;   // Sell if 5-minute price change < -5%
    protected int $maxHoldMinutes = 120;   // Sell after 120 minutes
    protected float $profitThreshold = 25.0; // Sell if profit >= 100%

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

        $tokenDataHelper = new SolanaTokenData();

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
                    continue;
                }

                $buyOrder = $call->orders->where('type', 'buy')->first();
                $tokenAddress = $call->token_address;

                if (!$buyOrder || !$buyOrder->amount_foreign) {
                    $this->warn("Skipping SolanaCall ID {$call->id}: no buy order or amount_foreign");
                    continue;
                }

                // Validate arguments for solana-sell.js
                if (!is_numeric($call->id) || empty($tokenAddress) || !is_numeric($buyOrder->amount_foreign)) {
                    $this->error("Invalid arguments for SolanaCall ID {$call->id}: id={$call->id}, token={$tokenAddress}, amount={$buyOrder->amount_foreign}");
                    SlackNotifier::error("Invalid arguments for SolanaCall #{$call->id} ({$tokenAddress}): id={$call->id}, amount={$buyOrder->amount_foreign}");
                    continue;
                }

                // Fetch latest data from QuickNode
                $data = $tokenDataHelper->getTokenData($tokenAddress);

                if ($data === null) {
                    SlackNotifier::error("QuickNode API failed or token not indexed for {$tokenAddress}.");
                    $this->warn("QuickNode API failed for {$tokenAddress}, forcing immediate sell to avoid blind holding.");

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
                    }

                    continue;
                }

                // Extract data from QuickNode response
                $currentPrice = $data['price'] ?? 0;
                $currentLiquidity = $data['liquidity']['usd'] ?? 0;
                $priceChangeM5 = $data['priceChange']['m5'] ?? 0;
                $currentMarketCap = $data['marketCap'] ?? 0;

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

                // Check sell conditions
                $tokenAmount = $buyOrder->amount_foreign;
                $holdTime = max(0, now()->diffInMinutes($buyOrder->created_at));

                $sellReason = null;
                if ($this->hasSignificantPriceDrop($priceChangeM5)) {
                    $sellReason = "negative M5 ({$priceChangeM5}% < {$this->m5Threshold}%)";
                } elseif ($this->hasReachedProfitThreshold($call, $call->market_cap, $currentMarketCap)) {
                    $sellReason = "profit threshold reached (>= {$this->profitThreshold}%)";
                } elseif ($holdTime > $this->maxHoldMinutes) {
                    $sellReason = "maximum hold time exceeded ({$holdTime} minutes)";
                }

                if ($sellReason) {
                    $this->info("Triggering sell for SolanaCall ID {$call->id} (M5: {$priceChangeM5}%, Reason: {$sellReason})");

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
                        SlackNotifier::error("Sell failed for SolanaCall #{$call->id} ({$tokenAddress}): " . $process->getErrorOutput());
                    } else {
                        $output = trim($process->getOutput());
                        $this->info("Sell completed for SolanaCall ID {$call->id}: {$output}");
                        SlackNotifier::success("Sell completed for SolanaCall #{$call->id} ({$tokenAddress}): {$output}");
                    }
                } else {
                    $this->info("Holding token {$tokenAddress}: M5 ({$priceChangeM5}% >= {$this->m5Threshold}%)");
                }

            } catch (\Exception $e) {
                $this->error("Error processing SolanaCall ID {$call->id}: {$e->getMessage()}");
                SlackNotifier::error("Error processing SolanaCall #{$call->id} ({$tokenAddress}): {$e->getMessage()}");
            }
        }

        $this->info('SolanaAutoSell run completed.');
    }

    /**
     * Check if the 5-minute price change is below the threshold.
     *
     * @param float $priceChangeM5
     * @return bool
     */
    private function hasSignificantPriceDrop(float $priceChangeM5): bool
    {
        return is_numeric($priceChangeM5) && $priceChangeM5 < $this->m5Threshold;
    }

    /**
     * Check if the profit has reached or exceeded 100% based on market cap.
     *
     * @param float|null $buyMarketCap
     * @param float|null $currentMarketCap
     * @return bool
     */
    private function hasReachedProfitThreshold(SolanaCall $solanaCall, ?float $buyMarketCap, ?float $currentMarketCap): bool
    {
        if (!is_numeric($buyMarketCap) || !is_numeric($currentMarketCap) || $currentMarketCap <= 0 || $buyMarketCap <= 0) {
            return false;
        }

        $profitPercent = $this->getCurrentProfit($buyMarketCap, $currentMarketCap);

        // --- always store snapshot ---
        $solanaCall->unrealizedProfits()->create([
            'unrealized_profit' => $profitPercent,
        ]);

        $minProfitToConsider = 3.0;   // Only track drops if previous profit was at least this
        $dropThreshold = 1.5;         // Only sell if drop from previous peak >= this

        // Initialize previous unrealized profits if not set
        if (!$solanaCall->previous_unrealized_profits) {
            $solanaCall->previous_unrealized_profits = $profitPercent;
            $solanaCall->save();
        }

        // Check if profit dropped significantly from previous peak
        if ($solanaCall->previous_unrealized_profits >= $minProfitToConsider) {
            $dropFromPrevious = $solanaCall->previous_unrealized_profits - $profitPercent;
            if ($dropFromPrevious >= $dropThreshold) {
                return true; // Sell due to significant dip
            }
        }

        // Update previous unrealized profits if current profit is higher
        if ($profitPercent > $solanaCall->previous_unrealized_profits) {
            $solanaCall->previous_unrealized_profits = $profitPercent;
            $solanaCall->save();
        }

        // Sell if profit reached the configured threshold
        if ($profitPercent >= $this->profitThreshold) {
            return true;
        }

        // --- NEW: Sell if stable for last 50 records ---
        if ($solanaCall->hasStableUnrealizedProfits()) {
            return true;
        }

        return false;
    }


    private function getCurrentProfit(?float $buyMarketCap, ?float $currentMarketCap)
    {
        return (($currentMarketCap - $buyMarketCap) / $buyMarketCap) * 100;
    }
}
