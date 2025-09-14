<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SolanaCall;
use Illuminate\Support\Facades\Log;

class SolanaCleanFailedCalls extends Command
{
    protected $signature = 'solana:clean-failed-calls';
    protected $description = 'Delete SolanaCall records with only failed orders or a buy order with amount_foreign = 0';

    public function handle()
    {
        // Find calls with only failed orders or a buy order with amount_foreign = 0
        $calls = SolanaCall::with('orders')->get()->filter(function ($call) {
            $orders = $call->orders;
            return $orders->isNotEmpty() && (
                    // Case 1: Only failed orders
                    ($orders->where('type', 'failed')->count() === $orders->count() &&
                        $orders->whereIn('type', ['buy', 'sell'])->isEmpty()) ||
                    // Case 2: Has a buy order with amount_foreign = 0 and no sell orders
                    ($orders->where('type', 'buy')->where('amount_foreign', 0)->isNotEmpty() &&
                        $orders->where('type', 'sell')->isEmpty())
                );
        });

        $this->info("Found {$calls->count()} SolanaCall records eligible for deletion.");

        foreach ($calls as $call) {
            try {
                $reason = $call->orders->where('type', 'failed')->count() === $call->orders->count()
                    ? "only failed orders ({$call->orders->count()})"
                    : "buy order with amount_foreign = 0";
                $this->info("Deleting SolanaCall ID {$call->id}: {$reason}.");
                Log::info("Deleting SolanaCall ID {$call->id}", [
                    'token_address' => $call->token_address,
                    'reason' => $reason,
                    'orders' => $call->orders->map(fn($order) => [
                        'type' => $order->type,
                        'amount_foreign' => $order->amount_foreign,
                    ])->toArray(),
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
