<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SolanaBlacklistContract;
use App\Models\SolanaCallOrder;
use App\Models\SolanaCall;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;
use App\Helpers\SlackNotifier;
use App\Services\SolanaContractScanner;

class PollBonkTrades extends Command
{
    protected $signature = 'solana:poll-bonk';
    protected $description = 'Poll BONK on 5m chart for scalps';

    // ✅ BONK token address (replace if different)
    private const BONK_ADDRESS = 'DezXAZ8z7PnrnRJjz3wXBoRgixCa6xjnB7YaB1pPB263';

    public function handle(): int
    {
        try {
            $tokenAddress = self::BONK_ADDRESS;

            // --- Skip blacklisted or active trades ---
            if ($this->shouldSkipToken($tokenAddress)) {
                Log::info("Skipping BONK — either blacklisted or already in play");
                return self::SUCCESS;
            }

            // --- BONK scanner ---
            $scanner = new SolanaContractScanner($tokenAddress, 'solana', trimmedChecks: true);

            // Force BONK-specific check
            if (!$scanner->canTradeWithBonkCheck()) {
                Log::info("❌ BONK metrics not favorable");
                return self::SUCCESS;
            }

            $data = $scanner->getTokenData();

            // --- Record trade call ---
            $call = SolanaCall::create([
                'token_name'    => 'BONK',
                'token_address' => $tokenAddress,
                'market_cap'    => $data['marketCap'] ?? 0,
                'liquidity_pool'=> $data['liquidity']['usd'] ?? 0,
                'strategy'      => 'BONK-5M',
                'reason_buy'    => $scanner->getBuyReason(),
            ]);

            // --- Launch buy script ---
            $process = new Process([
                'node',
                base_path('scripts/solana-buy.js'),
                '--identifier=' . $call->id,
                '--token=' . $tokenAddress,
                '--amount=0.002',
            ]);
            $process->setTimeout(360);
            $process->run();
            $process->wait();

            $exitCode    = $process->getExitCode();
            $output      = trim($process->getOutput());
            $errorOutput = trim($process->getErrorOutput());

            if ($exitCode === 0 && !empty($output)) {
                SlackNotifier::success("✅ BONK Buy completed for #{$call->id}\n```{$output}```");
            } elseif ($exitCode !== 0 || !empty($errorOutput)) {
                $errorMsg = $errorOutput ?: $output ?: 'Unknown error';
                SlackNotifier::error("❌ BONK Buy failed for #{$call->id}: {$errorMsg}");
            }

            return self::SUCCESS;

        } catch (Throwable $e) {
            $errorMsg = "Error polling BONK: " . $e->getMessage();
            $this->error($errorMsg);
            \Log::error($e);
            SlackNotifier::error($errorMsg);
            return self::FAILURE;
        }
    }

    private function shouldSkipToken(string $tokenAddress): bool
    {
        if (SolanaBlacklistContract::isBlacklisted($tokenAddress)) {
            Log::info("Skipping blacklisted token: {$tokenAddress}");
            return true;
        }

        $call = SolanaCall::where('token_address', $tokenAddress)
            ->latest('id')
            ->with('orders')
            ->first();

        if (!$call) return false;
        $orders = $call->orders;
        if ($orders->isEmpty()) return true;

        $hasBuy  = $orders->where('type', 'buy')->count() > 0;
        $hasSell = $orders->where('type', 'sell')->count() > 0;

        if ($hasBuy && !$hasSell) return true;
        if ($hasBuy && $hasSell) {
            $lastSell = SolanaCallOrder::whereHas('solanaCall', function ($q) use ($tokenAddress) {
                $q->where('token_address', $tokenAddress);
            })->where('type', 'sell')->latest('created_at')->first();

            if ($lastSell && $lastSell->created_at->gt(Carbon::now()->subMinutes(15))) return true;
        }

        return false;
    }
}
