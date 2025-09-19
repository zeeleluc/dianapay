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
    protected $description = 'Automatically sell tokens based on trailing stop profit logic.';

    protected float $minLiquidity = 1000;      // Minimum liquidity to consider sell
    protected float $trailingDropPercent = -0.25;  // Sell if profit drops by this % from peak

    public function handle()
    {
        $calls = SolanaCall::with('orders')->get()->filter(fn($call) =>
            $call->orders->where('type', 'buy')->count() > 0 &&
            $call->orders->where('type', 'sell')->count() === 0
        );

        $this->info("Found {$calls->count()} calls eligible for potential sell.");

        $tokenDataHelper = new SolanaTokenData();

        foreach ($calls as $call) {
            try {
                $buyOrder = $call->orders->where('type', 'buy')->first();
                $tokenAddress = $call->token_address;

                if (!$buyOrder || !$buyOrder->amount_foreign) {
                    $this->warn("Skipping SolanaCall ID {$call->id}: no buy order or amount_foreign.");
                    continue;
                }

                // Validate arguments
                if (!is_numeric($call->id) || empty($tokenAddress) || !is_numeric($buyOrder->amount_foreign)) {
                    SlackNotifier::error("Invalid arguments for SolanaCall #{$call->id} ({$tokenAddress})");
                    continue;
                }

                // Fetch latest token data
                $data = $tokenDataHelper->getTokenData($tokenAddress);

                if (!$data) {
                    SlackNotifier::error("API failed for token {$tokenAddress}, forcing immediate sell.");
                    $this->forceSell($call, $tokenAddress, $buyOrder->amount_foreign);
                    continue;
                }

                $currentPrice = $data['price'] ?? 0;
                $currentLiquidity = $data['liquidity']['usd'] ?? 0;
                $currentMarketCap = $data['marketCap'] ?? 0;

                if (!is_numeric($currentPrice) || $currentPrice <= 0 || $currentLiquidity < $this->minLiquidity) {
                    $this->warn("Skipping {$tokenAddress}: invalid price or insufficient liquidity (\${$currentLiquidity}).");
                    continue;
                }

                // Calculate current profit %
                $profitPercent = $this->getCurrentProfit($call->market_cap, $currentMarketCap);

                // Store snapshot
                $call->unrealizedProfits()->create([
                    'unrealized_profit' => $profitPercent,
                    'buy_market_cap' => $call->market_cap,
                    'current_market_cap' => $currentMarketCap,
                ]);

                // Determine if we should sell
                if ($this->shouldSell($call, $profitPercent)) {
                    $this->info("Selling token {$tokenAddress} (profit: {$profitPercent}%)");
                    $this->executeSell($call, $tokenAddress, $buyOrder->amount_foreign);
                } else {
                    $this->info("Holding token {$tokenAddress} (profit: {$profitPercent}%)");
                }

            } catch (\Exception $e) {
                $this->error("Error processing SolanaCall ID {$call->id}: {$e->getMessage()}");
                SlackNotifier::error("Error processing SolanaCall #{$call->id} ({$tokenAddress}): {$e->getMessage()}");
            }
        }

        $this->info('SolanaAutoSell run completed.');
    }

    private function shouldSell(SolanaCall $call, float $profitPercent): bool
    {
        // Initialize previous peak profit if not set
        if ($call->previous_unrealized_profits === null) {
            $call->previous_unrealized_profits = $profitPercent;
            $call->save();
            return false;
        }

        // Update peak if current profit is higher
        if ($profitPercent > $call->previous_unrealized_profits) {
            $call->previous_unrealized_profits = $profitPercent;
            $call->save();
            return false; // profit rising → hold
        }

        // Check if profit dropped by trailing threshold
        $drop = $call->previous_unrealized_profits - $profitPercent;
        if ($drop >= $this->trailingDropPercent) {
            $call->reason_sell = "Profit dropped {$drop}% from peak ({$profitPercent}%)";
            $call->save();
            return true;
        }

        return false;
    }

    private function getCurrentProfit(?float $buyMarketCap, ?float $currentMarketCap): float
    {
        if (!$buyMarketCap || !$currentMarketCap) return 0;
        return (($currentMarketCap - $buyMarketCap) / $buyMarketCap) * 100;
    }

    private function forceSell(SolanaCall $call, string $tokenAddress, float $amount)
    {
        $process = new Process([
            'node',
            base_path('scripts/solana-sell.js'),
            "--identifier={$call->id}",
            "--token={$tokenAddress}",
            "--amount={$amount}",
        ]);

        $process->setTimeout(360);
        $process->run();
        $process->wait();

        if (!$process->isSuccessful()) {
            $this->error("Forced sell failed for SolanaCall ID {$call->id}: " . $process->getErrorOutput());
            SlackNotifier::error("Forced sell failed for SolanaCall #{$call->id} ({$tokenAddress})");
        } else {
            $output = trim($process->getOutput());
            $this->info("Forced sell completed for SolanaCall ID {$call->id}: {$output}");
            SlackNotifier::success("Forced sell completed for SolanaCall #{$call->id} ({$tokenAddress})");
        }
    }

    private function executeSell(SolanaCall $call, string $tokenAddress, float $amount)
    {
        $process = new Process([
            'node',
            base_path('scripts/solana-sell.js'),
            "--identifier={$call->id}",
            "--token={$tokenAddress}",
            "--amount={$amount}",
        ]);

        $process->setTimeout(360);
        $process->run();
        $process->wait();

        if (!$process->isSuccessful()) {
            $this->error("Sell failed for SolanaCall ID {$call->id}: " . $process->getErrorOutput());
            SlackNotifier::error("Sell failed for SolanaCall #{$call->id} ({$tokenAddress})");
        } else {
            $output = trim($process->getOutput());
            $this->info("Sell completed for SolanaCall ID {$call->id}: {$output}");
            SlackNotifier::success("Sell completed for SolanaCall #{$call->id} ({$tokenAddress}): {$output}");
        }
    }
}
