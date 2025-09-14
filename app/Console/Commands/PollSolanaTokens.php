<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;
use App\Models\SolanaCall;
use App\Helpers\SlackNotifier;
use App\Services\SolanaContractScanner;

class PollSolanaTokens extends Command
{
    protected $signature = 'poll-solana-tokens
                            {--testing=0 : If 1, use tiny buy amounts for testing}';

    protected $description = 'Poll trending + boosted Solana tokens, detect pump potential, and buy them';

    public function handle(): int
    {
        $testing = (bool) $this->option('testing');
        $this->info('Starting trending/boosted memecoin scan...' . ($testing ? ' [TEST MODE]' : ''));

        try {

            $boostedResponse = Http::get('https://api.dexscreener.com/token-boosts/latest/v1')->json();
            $boostedPairs = $boostedResponse ?? [];

            $topBoostedResponse = Http::get('https://api.dexscreener.com/token-boosts/top/v1')->json();
            $topBoostedPairs = $topBoostedResponse ?? [];

            // --- Normalize all tokens ---
            $allTokens = [];

            // Collect token addresses first
            foreach (array_merge($boostedPairs, $topBoostedPairs) as $b) {
                if (!isset($b['tokenAddress']) || ($b['chainId'] ?? '') !== 'solana') continue;

                $allTokens[] = [
                    'tokenAddress' => $b['tokenAddress'],
                    'chainId'      => $b['chainId'] ?? 'solana',
                    'boosted'      => true,
                    'extra'        => $b,
                ];
            }

            // Separate call to fetch token info
            foreach ($allTokens as &$token) {
                $info = $this->getTokenInfo($token['tokenAddress'], $token['chainId']);

                $token['tokenName'] = substr($info['name'] ?? 'Unknown Token', 0, 100); // truncate if needed
                $token['ticker']    = $info['symbol'] ?? null;
            }

            $matchesFound = 0;
            $skippedTokens = [];
            $failedBuys = [];

            foreach ($allTokens as $token) {
                if ($matchesFound >= 10) break;

                $tokenAddress = $token['tokenAddress'] ?? null;
                $chain = $token['chainId'] ?? 'solana';
                $tokenName = $token['tokenName'] ?? 'Unknown Token';
                $isBoosted = $token['boosted'] ?? false;

                if (!$tokenAddress || $chain !== 'solana') continue;

                if (SolanaCall::where('token_address', $tokenAddress)->exists()) continue;

                // 🔍 Run scanner
                $scanner = new SolanaContractScanner($tokenAddress, $chain);
                $scanner->setBoosted($isBoosted);

                $pairResponse = Http::get("https://api.dexscreener.com/token-pairs/v1/{$chain}/{$tokenAddress}")->json();
                $pair = $pairResponse[0] ?? [];

                if (!$scanner->canTrade($pair)) {
                    $skippedTokens[] = "Scanner rejected {$tokenName} ({$tokenAddress})";
                    continue;
                }

                // ✅ Fetch pair details safely
                $pairResponse = Http::get("https://api.dexscreener.com/token-pairs/v1/{$chain}/{$tokenAddress}")->json();
                $pair = is_array($pairResponse) && !empty($pairResponse[0]) ? $pairResponse[0] : [];
                $marketCap = $pair['marketCap'] ?? 0;
                $liquidityUsd = $pair['liquidity']['usd'] ?? 0;
                $volume24h = $pair['volume']['h24'] ?? 0;
                $pairCreatedAtMs = $pair['pairCreatedAt'] ?? 0;
                $pairCreatedAt = $pairCreatedAtMs > 0 ? (int) ($pairCreatedAtMs / 1000) : time();
                $ageMinutes = max(0, round((time() - $pairCreatedAt) / 60));
                $devSold = $token['devSold'] ?? false;

                $boostLabel = $isBoosted ? ' [BOOSTED]' : '';
                SlackNotifier::success("Found trending{$boostLabel} Solana token: {$tokenName} (MC: \${$marketCap}, Liq: \${$liquidityUsd}, Age: {$ageMinutes}m)");

                // Save call in DB
                $call = SolanaCall::create([
                    'token_name' => substr($tokenName, 0, 100), // limit to 100 chars
                    'token_address' => $tokenAddress,
                    'age_minutes' => $ageMinutes,
                    'market_cap' => $marketCap,
                    'volume_24h' => $volume24h,
                    'liquidity_pool' => $liquidityUsd,
                    'strategy' => $isBoosted ? 'BOOSTED-BUY' : 'TRENDING-BUY',
                    'dev_sold' => $devSold,
                    'dex_paid_status' => $token['dexPaidStatus'] ?? false,
                ]);

                $this->info("Saved SolanaCall ID: {$call->id} - Token: {$tokenName} ({$tokenAddress}){$boostLabel}");

                // --- Node buy process ---
                $buyAmount = $testing ? 0.001 : 0.003;
                if (!$testing) {
                    if ($marketCap >= 30000 && $liquidityUsd >= 30000) $buyAmount = 0.02;
                    elseif ($marketCap >= 15000 && $liquidityUsd >= 20000) $buyAmount = 0.01;
                    else $buyAmount = 0.005;
                }

                SlackNotifier::info("Launching buy for SolanaCall #{$call->id}: {$tokenName} ({$buyAmount} SOL){$boostLabel}");

                $process = new Process([
                    'node',
                    base_path('scripts/solana-buy.js'),
                    '--identifier=' . $call->id,
                    '--token=' . $tokenAddress,
                    '--amount=' . $buyAmount,
                ]);
                $process->setTimeout(360);
                $process->run(); // <-- run() waits for completion

                $this->info("Started buy process for SolanaCall ID {$call->id} (PID: " . $process->getPid() . ")");
                $process->wait();

                $exitCode = $process->getExitCode();
                $output = trim($process->getOutput());
                $errorOutput = trim($process->getErrorOutput());

                if ($exitCode === 0 && !empty($output)) {
                    SlackNotifier::success("✅ Buy completed for #{$call->id} ({$tokenName}): Exit 0\n```{$output}```");
                    $this->info("Buy success for #{$call->id}");
                } elseif ($exitCode !== 0 || !empty($errorOutput)) {
                    $errorMsg = $errorOutput ?: $output ?: 'Unknown error (exit ' . $exitCode . ')';
                    SlackNotifier::error("❌ Buy failed for #{$call->id} ({$tokenName}): {$errorMsg}");
                    $this->error("Buy failed for #{$call->id}: {$errorMsg}");
                    $failedBuys[] = "#{$call->id}: {$errorMsg}";
                } else {
                    SlackNotifier::info("Buy completed for #{$call->id} ({$tokenName}) (no output)");
                }

                $matchesFound++;
            }

            if (!empty($skippedTokens)) {
                $skipsMsg = "Skipped " . count($skippedTokens) . " tokens: " . implode('; ', array_slice($skippedTokens, 0, 5)) . (count($skippedTokens) > 5 ? '...' : '');
//                SlackNotifier::warning($skipsMsg);
            }
            if (!empty($failedBuys)) {
                $failsMsg = "Failed buys: " . implode('; ', array_slice($failedBuys, 0, 3)) . (count($failedBuys) > 3 ? '...' : '');
//                SlackNotifier::error($failsMsg);
            }

            $summary = "Poll complete: Processed {$matchesFound} tokens.";
            $this->info($summary);

            return self::SUCCESS;

        } catch (Throwable $e) {
            $errorMsg = "Error polling tokens: " . $e->getMessage();
            $this->error($errorMsg);
            \Log::error($e);
            SlackNotifier::error($errorMsg);
            return self::FAILURE;
        }
    }
    private function getTokenInfo(string $tokenAddress, string $chain): array
    {
        $response = Http::get("https://api.dexscreener.com/token-pairs/v1/{$chain}/{$tokenAddress}");

        if ($response->failed()) {
            \Log::warning("Failed to fetch token info for {$tokenAddress}");
            return [];
        }

        $data = $response->json();
        $pair = $data[0] ?? [];

        return [
            'name'   => $pair['baseToken']['name'] ?? null,
            'symbol' => $pair['baseToken']['symbol'] ?? null,
        ];
    }
}
