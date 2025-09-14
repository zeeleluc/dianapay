<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SolanaCall;
use Illuminate\Support\Facades\Log;

class SolanaCleanFailedCalls extends Command
{
    protected $signature = 'solana:clean-failed-calls';
    protected $description = 'Delete SolanaCall records that have only failed orders';

    public function handle()
    {
        // Find calls with only failed orders
        $calls = SolanaCall::with('orders')->get()->filter(function ($call) {
            $orders = $call->orders;
            return $orders->isNotEmpty() &&
                $orders->where('type', 'failed')->count() === $orders->count() &&
                $orders->whereIn('type', ['buy', 'sell'])->isEmpty();
        });

        $this->info("Found {$calls->count()} SolanaCall records with only failed orders.");

        foreach ($calls as $call) {
            try {
                $this->info("Deleting SolanaCall ID {$call->id} with {$call->orders->count()} failed orders.");
                Log::info("Deleting SolanaCall ID {$call->id}", [
                    'token_address' => $call->token_address,
                    'failed_orders' => $call->orders->count(),
                ]);
                $call->delete(); // Orders deleted via ON DELETE CASCADE
            } catch (\Exception $e) {
                $this->error("Failed to delete SolanaCall ID {$call->id}: {$e->getMessage()}");
                Log::error("Failed to delete SolanaCall ID {$call->id}: {$e->getMessage()}");
            }
        }

        $this->info('SolanaCleanFailedCalls run completed.');
    }
}
