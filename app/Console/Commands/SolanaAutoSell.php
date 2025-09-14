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
    protected $description = 'Automatically sell tokens that reached market cap profit/loss thresholds';

    protected float $profitThreshold = 5.0; // 2% profit
    protected float $lossThreshold   = -10.0; // -1% loss

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
                $originalMarketCap = $call->market_cap;

                if (!$buyOrder || !$buyOrder->amount_foreign) {
                    $this->warn("Skipping SolanaCall ID {$call->id}: no buy order or amount is 0");
                    continue;
                }

                // Fetch latest market cap
                $res = Http::timeout(5)->get("https://api.dexscreener.com/latest/dex/tokens/{$tokenAddress}");
                if (!$res->successful() || empty($res->json('pairs'))) {
                    $this->warn("Failed to fetch market cap for token {$tokenAddress}");
                    continue;
                }

                $currentMarketCap = $res->json('pairs.0.marketCap');
                $profitPct = (($currentMarketCap - $originalMarketCap) / $originalMarketCap) * 100;

                $this->info("Token {$tokenAddress} PnL (via market cap): {$profitPct}%");

                // Check thresholds
                if ($profitPct >= $this->profitThreshold || $profitPct <= $this->lossThreshold) {
                    $this->info("Triggering sell for SolanaCall ID {$call->id} (PnL: {$profitPct}%)");

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
