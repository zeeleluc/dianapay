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

class PollHighEndTokens extends Command
{
    protected $signature = 'solana:poll-highend';
    protected $description = 'Poll high-end tokens on 5m chart for scalps';

    // ✅ High-end tokens list
    private const TOKENS = [
        'BONK' => [
            'address' => 'DezXAZ8z7PnrnRJjz3wXBoRgixCa6xjnB7YaB1pPB263',
            'checker' => 'canTradeWithBonkCheck',
            'strategy' => 'BONK-5M',
            'amount' => 0.002,
        ],
        'JLP' => [
            'address' => '27G8MtK7VtTcCHkpASjSDdkWWYfoqT6ggEuKidVJidD4',
            'checker' => 'canTradeWithJlpCheck',
            'strategy' => 'JLP-5M',
            'amount' => 0.002,
        ],
        // Add more high-end tokens here
    ];

    public function handle(): int
    {
        foreach (self::TOKENS as $name => $config) {
            try {
                $tokenAddress = $config['address'];

                if ($this->shouldSkipToken($tokenAddress)) {
                    Log::info("Skipping {$name} — blacklisted or active trade");
                    continue;
                }

                $scanner = new SolanaContractScanner($tokenAddress, 'solana', trimmedChecks: true);

                // Token-specific metrics check
                if (!method_exists($scanner, $config['checker']) || !$scanner->{$config['checker']}()) {
                    Log::info("❌ {$name} metrics not favorable");
                    continue;
                }

                $data = $scanner->getTokenData();

                // Record trade call
                $call = SolanaCall::create([
                    'token_name'    => $name,
                    'token_address' => $tokenAddress,
                    'market_cap'    => $data['marketCap'] ?? 0,
                    'liquidity_pool'=> $data['liquidity']['usd'] ?? 0,
                    'strategy'      => $config['strategy'],
                    'reason_buy'    => $scanner->getBuyReason(),
                ]);

                // Launch buy script
                $process = new Process([
                    'node',
                    base_path('scripts/solana-buy.js'),
                    '--identifier=' . $call->id,
                    '--token=' . $tokenAddress,
                    '--amount=' . $config['amount'],
                ]);
                $process->setTimeout(360);
                $process->run();
                $process->wait();

                $exitCode    = $process->getExitCode();
                $output      = trim($process->getOutput());
                $errorOutput = trim($process->getErrorOutput());

                if ($exitCode === 0 && !empty($output)) {
                    SlackNotifier::success("✅ {$name} Buy completed for #{$call->id}\n```{$output}```");
                } elseif ($exitCode !== 0 || !empty($errorOutput)) {
                    $errorMsg = $errorOutput ?: $output ?: 'Unknown error';
                    SlackNotifier::error("❌ {$name} Buy failed for #{$call->id}: {$errorMsg}");
                }

            } catch (Throwable $e) {
                $errorMsg = "Error polling {$name}: " . $e->getMessage();
                $this->error($errorMsg);
                Log::error($e);
                SlackNotifier::error($errorMsg);
            }
        }

        return self::SUCCESS;
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
