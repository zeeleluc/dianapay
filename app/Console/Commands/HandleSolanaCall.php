<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SolanaCall;
use Symfony\Component\Process\Process;
use Throwable;

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
            $this->info("Sniping SolanaCall ID: {$id} - Token: {$call->token_name} ({$call->token_address})");

            // In handle()
            $process = new Process(['node', base_path('scripts/solana-snipe.js'), '--token=' . $call->token_address, '--amount=0.0001', '--poll']);
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
            \Log::info("Solana snipe ID {$id} success: " . $output);  // Log to storage/logs/laravel.log

            // Update DB with output
            $call->update(['status' => 'sniped', 'output' => $output]);

            $this->info("SolanaCall {$id} sniped successfully!");
            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error("Error handling SolanaCall {$id}: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
