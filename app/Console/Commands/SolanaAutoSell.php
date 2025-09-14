<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SolanaCall;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SolanaAutoSell extends Command
{
    protected $signature = 'solana:auto-sell';
    protected $description = 'Automatically sell tokens based on momentum, profit, and trailing stop';

    protected float $profitThreshold = 10.0;  // Sell if profit > 10% and momentum slows
    protected float $lossThreshold   = -7.0;  // Hard stop-loss
    protected float $minLiquidity    = 1000;  // Minimum liquidity for sell
    protected float $m5Threshold     = -2.0;  // Sell if 5-minute price change < -2%
    protected float $m5SlowThreshold = 2.0;   // Momentum considered "slow" for TP
    protected float $trailingStop     = -3.0;  // Trailing stop: sell if drops 3% from peak
    protected float $h1Threshold      = 0.0;   // Confirm M5 sell with H1 trend

    public function handle()
    {
        $calls = SolanaCall::with('orders')->get()->filter(function ($call) {
            $hasBuy = $call->orders->where('type', 'buy')->count() > 0;
            $hasSell = $call->orders->where('type', 'sell')->count() > 0;
            return $hasBuy && !$hasSell;
        });

        $this->info("Found {$calls->count()} calls eligible for potential sell.");

        foreach ($calls as $call) {
            try {
                // Check failures first
                $failures = $call->orders->where('type', 'failed')->count();
                if ($failures >= 10) {
                    $this->warn("Deleting SolanaCall ID {$call->id}: {$failures} failed attempts.");
                    $call->delete();
                    continue;
                }

                $buyOrder = $call->orders->where('type', 'buy')->first();
                $tokenAddress = $call->token_address;

                if (!$buyOrder || !$buyOrder->amount_foreign || !$buyOrder->price) {
                    $this->warn("Skipping SolanaCall ID {$call->id}: no buy order, amount, or price");
                    continue;
                }

                // Fetch latest data
                $res = Http::timeout(5)->get("https://api.dexscreener.com/latest/dex/tokens/{$tokenAddress}");
                if (!$res->successful() || empty($res->json('pairs'))) {
                    $this->warn("Failed to fetch data for token {$tokenAddress}");
                    continue;
                }

                $currentPrice = $res->json('pairs.0.priceUsd') ?? 0;
                $currentLiquidity = $res->json('pairs.0.liquidity.usd') ?? 0;
                $priceChangeM5 = $res->json('pairs.0.priceChange.m5') ?? 0;
                $priceChangeH1 = $res->json('pairs.0.priceChange.h1') ?? 0;

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

                // Calculate profit/loss based on price
                $buyPrice = $buyOrder->price;
                $profitPct = (($currentPrice - $buyPrice) / $buyPrice) * 100;

                // Update peak profit (assuming stored in SolanaCall model)
                $peakProfit = $call->peak_profit ?? $profitPct;
                if ($profitPct > $peakProfit) {
                    $call->peak_profit = $profitPct;
                    $call->save();
                    $peakProfit = $profitPct;
                }

                // Check trailing stop
                $trailingLoss = $profitPct - $peakProfit;

                $this->info("Token {$tokenAddress} PnL: {$profitPct}%, Peak: {$peakProfit}%, M5: {$priceChangeM5}%, H1: {$priceChangeH1}%");

                // Sell conditions
                $sellReason = null;
                if ($priceChangeM5 < $this->m5Threshold && $priceChangeH1 < $this->h1Threshold) {
                    $sellReason = "negative M5 ({$priceChangeM5}%) with weak H1 ({$priceChangeH1}%)";
                } elseif ($profitPct >= $this->profitThreshold && $priceChangeM5 < $this->m5SlowThreshold) {
                    $sellReason = "profit ({$profitPct}%) with slow M5 ({$priceChangeM5}%)";
                } elseif ($profitPct <= $this->lossThreshold) {
                    $sellReason = "hit stop-loss ({$profitPct}%)";
                } elseif ($trailingLoss <= $this->trailingStop) {
                    $sellReason = "trailing stop hit ({$trailingLoss}% from peak)";
                }

                if ($sellReason) {
                    $this->info("Triggering sell for SolanaCall ID {$call->id} (PnL: {$profitPct}%, M5: {$priceChangeM5}%, Reason: {$sellReason})");

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
                    }
                } else {
                    $this->info("Holding token {$tokenAddress}: PnL ({$profitPct}%), M5 ({$priceChangeM5}%) still viable");
                }

            } catch (\Exception $e) {
                $this->error("Error processing SolanaCall ID {$call->id}: {$e->getMessage()}");
                Log::error("SolanaAutoSell error: " . $e->getMessage(), ['call_id' => $call->id]);
            }
        }

        $this->info('SolanaAutoSell run completed.');
    }
}
