<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SolanaCall;
use Symfony\Component\Process\Process;
use Throwable;
use Illuminate\Support\Facades\Http;

class HandleSolanaCall extends Command
{
    protected $signature = 'handle-solana-call {--id= : The ID of the SolanaCall to handle}';

    protected $description = 'Handle a specific SolanaCall by ID (snipes token via JS script)';

    public function handle(): int
    {
        $id = $this->option('id');

        if (!$id || !is_numeric($id)) {
            $this->error('Invalid or missing --id option. Usage: php artisan handle-solana-call --id=1');
            return self::FAILURE;
        }

        $call = SolanaCall::find($id);

        if (!$call) {
            $this->error("SolanaCall with ID {$id} not found.");
            return self::FAILURE;
        }

        try {
            $this->info("Evaluating SolanaCall ID: {$id} - Token: {$call->token_name} ({$call->token_address})");

            // --- Check Market Cap and Liquidity ---
            $minMarketCap = 9_000; // USD
            $minLiquidity = 10_000; // USD

            $dexResponse = Http::get("https://api.dexscreener.com/latest/dex/tokens/{$call->token_address}");
            $pairs = $dexResponse->json('pairs', []);

            $marketCap = $pairs[0]['marketCap'] ?? 0;
            $liquidityUsd = $pairs[0]['liquidity']['usd'] ?? 0;

            if ($marketCap < $minMarketCap || $liquidityUsd < $minLiquidity) {
                $this->warn("Skipping token due to low metrics. Market Cap: {$marketCap} USD, Liquidity: {$liquidityUsd} USD");
                \Log::warning("SolanaCall ID {$id} skipped: Market Cap {$marketCap}, Liquidity {$liquidityUsd}");
                $call->update([
                    'status' => 'skipped',
                    'output' => "Skipped due to low Market Cap or LP. Market Cap: {$marketCap}, LP: {$liquidityUsd}"
                ]);
                return self::SUCCESS;
            }

            // --- Execute JS sniping script ---
            $this->info("Sniping SolanaCall ID: {$id} - Token: {$call->token_name} ({$call->token_address})");

            // --- Determine buy amount based on metrics ---
            $buyAmount = 0.001; // default

            // Simple tiering example
            if ($marketCap >= 100_000 && $liquidityUsd >= 50_000) {
                $buyAmount = 0.005;
            } elseif ($marketCap >= 50_000 && $liquidityUsd >= 20_000) {
                $buyAmount = 0.004;
            } elseif ($marketCap >= 20_000 && $liquidityUsd >= 15_000) {
                $buyAmount = 0.003;
            } elseif ($marketCap >= 10_000 && $liquidityUsd >= 10_000) {
                $buyAmount = 0.002;
            } // otherwise keep 0.001

            $process = new Process([
                'node',
                base_path('scripts/solana-snipe.js'),
                '--identifier=' . $call->id,
                '--token=' . $call->token_address,
                '--amount=' . $buyAmount,
                '--poll'
            ]);

            $process->setTimeout(360);  // 6 mins
            $process->run();

            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();

            if (!$process->isSuccessful()) {
                $this->error("Script failed: " . $errorOutput);
                \Log::error("Solana snipe ID {$id} error: " . $errorOutput . "\nOutput: " . $output);
                return self::FAILURE;
            }

            $this->info("Full script output:\n" . $output);
            \Log::info("Solana snipe ID {$id} success: " . $output);

            $call->update(['status' => 'sniped', 'output' => $output]);

            $this->info("SolanaCall {$id} sniped successfully!");
            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error("Error handling SolanaCall {$id}: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
