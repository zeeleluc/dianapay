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
    protected $description = 'Automatically sell tokens that reached profit/loss thresholds';

    // Profit/loss thresholds
    protected float $profitThreshold = 10.0; // 10% profit
    protected float $lossThreshold   = -5.0; // -5% loss

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
                $buyOrder = $call->orders->where('type', 'buy')->first();
                $tokenAddress = $call->token_address;
                $buyAmountForeign = $buyOrder->amount_foreign;
                $costBasisUsd = $buyOrder->amount_sol * 150; // Approximate SOL price in USD; you may fetch real price if needed

                // Fetch current token price from DexScreener
                $res = Http::timeout(5)->get("https://api.dexscreener.com/latest/dex/tokens/{$tokenAddress}");
                if (!$res->successful() || empty($res->json('pairs'))) {
                    $this->warn("Failed to fetch price for token {$tokenAddress}");
                    continue;
                }

                $priceUsd = $res->json('pairs.0.priceUsd');
                $currentValueUsd = $buyAmountForeign * $priceUsd;
                $profitPct = (($currentValueUsd - $costBasisUsd) / $costBasisUsd) * 100;

                $this->info("Token {$tokenAddress} PnL: {$profitPct}%");

                if ($profitPct >= $this->profitThreshold || $profitPct <= $this->lossThreshold) {
                    $this->info("Triggering sell for SolanaCall ID {$call->id} (PnL: {$profitPct}%)");

                    $buyOrder = $call->orders()->where('type', 'buy')->first();

                    if (!$buyOrder || !$buyOrder->amount_foreign) {
                        $this->warn("Skipping sell for SolanaCall ID {$call->id}: no buy order found or amount is 0");
                        continue;
                    }

                    $tokenAmount = $buyOrder->amount_foreign;
                    $process = new Process([
                        'node',
                        base_path('scripts/solana-sell.js'),
                        '--identifier=' . $call->id,
                        '--token=' . $call->token_address,
                        '--amount=' . $tokenAmount,
                    ]);
                    $process->setTimeout(360);
                    $process->run();

                    $this->info("Started sell process for SolanaCall ID {$call->id} (PID: " . $process->getPid() . ")");
                    $process->wait();

                    if (!$process->isSuccessful()) {
                        $this->error("Sell failed for SolanaCall ID {$call->id}: " . $process->getErrorOutput());
                    } else {
                        $this->info("Sell completed for SolanaCall ID {$call->id}: " . $process->getOutput());
                    }

                } else {
                    $this->info("Skipping token {$tokenAddress}: PnL ({$profitPct}%) not within thresholds");
                }
            } catch (\Exception $e) {
                $this->error("Error processing SolanaCall ID {$call->id}: {$e->getMessage()}");
                Log::error("SolanaAutoSell error: " . $e->getMessage(), ['call_id' => $call->id]);
            }
        }

        $this->info('SolanaAutoSell run completed.');
    }
}
