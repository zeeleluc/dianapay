<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SolanaCall;
use Symfony\Component\Process\Process;
use Throwable;
use Illuminate\Support\Facades\Http;
use App\Helpers\SlackNotifier;

class HandleSolanaCall extends Command
{
    protected $signature = 'handle-solana-call {--id= : The ID of the SolanaCall to handle}';

    protected $description = 'Handle a specific SolanaCall by ID (snipes token via JS script)';

    public function handle(): int
    {
        $id = $this->option('id');

        if (!$id || !is_numeric($id)) {
            $msg = 'Invalid or missing --id option. Usage: php artisan handle-solana-call --id=1';
            $this->error($msg);
            SlackNotifier::error($msg);
            return self::FAILURE;
        }

        $call = SolanaCall::find($id);

        if (!$call) {
            $msg = "SolanaCall with ID {$id} not found.";
            $this->error($msg);
            SlackNotifier::error($msg);
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
                $msg = "Skipping token due to low metrics. Market Cap: {$marketCap} USD, Liquidity: {$liquidityUsd} USD";
                $this->warn($msg);
                \Log::warning("SolanaCall ID {$id} skipped: Market Cap {$marketCap}, Liquidity {$liquidityUsd}");
                SlackNotifier::warning("Skipped SolanaCall {$id} ({$call->token_name}) — MC: {$marketCap}, LP: {$liquidityUsd}");

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

            if ($marketCap >= 100_000 && $liquidityUsd >= 50_000) {
                $buyAmount = 0.015;
            } elseif ($marketCap >= 50_000 && $liquidityUsd >= 20_000) {
                $buyAmount = 0.01;
            } elseif ($marketCap >= 20_000 && $liquidityUsd >= 15_000) {
                $buyAmount = 0.008;
            } elseif ($marketCap >= 10_000 && $liquidityUsd >= 10_000) {
                $buyAmount = 0.004;
            }

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
                $msg = "Script failed for SolanaCall {$id}: " . $errorOutput;
                $this->error($msg);
                \Log::error("Solana snipe ID {$id} error: " . $errorOutput . "\nOutput: " . $output);
                SlackNotifier::error($msg);
                return self::FAILURE;
            }

            $this->info("Full script output:\n" . $output);
            \Log::info("Solana snipe ID {$id} success: " . $output);

            $call->update(['status' => 'sniped', 'output' => $output]);

            $msg = "✅ SolanaCall {$id} SNIPED successfully! ({$call->token_name}) @ {$buyAmount} SOL";
            $this->info($msg);
            SlackNotifier::success($msg);

            return self::SUCCESS;

        } catch (Throwable $e) {
            $msg = "Error handling SolanaCall {$id}: " . $e->getMessage();
            $this->error($msg);
            \Log::error($msg);
            SlackNotifier::error($msg);
            return self::FAILURE;
        }
    }
}
