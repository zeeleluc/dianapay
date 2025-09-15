<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\SolanaTokenData;
use App\Models\SolanaCall;
use App\Models\SolanaCallOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;
use App\Helpers\SlackNotifier;

class SolanaWalletTracker extends Command
{
    protected $signature = 'solana:wallet-tracker';
    protected $description = 'Track specified wallets for their most recent meme coin buy (within 1 minute) and purchase it';

    // Array of wallets to track
    protected array $walletsToTrack = [
        'suqh5sHtr8HyJ7q8scBimULPkPpA557prMG47xCHQfK',
        // Add more wallets here, e.g.:
        // 'anotherWalletAddress123...',
        // 'yetAnotherWalletAddress456...',
    ];

    protected string $tokenAddress;

    public function handle(): int
    {
        try {
            $matchesFound = 0;
            $skippedTokens = [];

            foreach ($this->walletsToTrack as $wallet) {
                if ($matchesFound >= 10) break; // Limit to 10 buys per run

                $token = $this->pollLatestWalletToken($wallet);

                if (!$token) {
                    $this->info("No recent meme coin buy found for wallet {$wallet}");
                    continue;
                }

                $tokenAddress = $token['tokenAddress'] ?? null;
                $tokenName = $token['tokenName'] ?? 'Unknown Token';
                $chain = $token['chainId'] ?? 'solana';

                if (!$tokenAddress || $chain !== 'solana') {
                    $this->warn("Skipping invalid token for wallet {$wallet}: {$tokenAddress}");
                    continue;
                }

                if ($this->shouldSkipToken($tokenAddress)) {
                    $skippedTokens[] = "Already traded {$tokenName} ({$tokenAddress})";
                    continue;
                }

                // Fetch token data for validation
                $tokenDataHelper = new SolanaTokenData();
                $data = $tokenDataHelper->getTokenData($tokenAddress);
                if (!$data) {
                    $this->warn("Skipping {$tokenName} ({$tokenAddress}): Failed to fetch token data");
                    continue;
                }

                $marketCap = $data['marketCap'] ?? 0;
                $liquidityUsd = $data['liquidity']['usd'] ?? 0;
                $volume24h = $data['volume']['h24'] ?? 0;

                // Fetch pair data for age
                $pairResponse = Http::get("https://api.dexscreener.com/token-pairs/v1/{$chain}/{$tokenAddress}")->json();
                $pair = is_array($pairResponse) && !empty($pairResponse[0]) ? $pairResponse[0] : [];
                $pairCreatedAtMs = $pair['pairCreatedAt'] ?? 0;
                $pairCreatedAt = $pairCreatedAtMs > 0 ? (int)($pairCreatedAtMs / 1000) : time();
                $ageMinutes = max(0, round((time() - $pairCreatedAt) / 60));

                SlackNotifier::success("Detected recent wallet buy: {$tokenName} (MC: \${$marketCap}, Liq: \${$liquidityUsd}, Age: {$ageMinutes}m, Wallet: {$wallet})");

                // Save call in DB
                $call = SolanaCall::create([
                    'token_name' => substr($tokenName, 0, 100),
                    'token_address' => $tokenAddress,
                    'age_minutes' => $ageMinutes,
                    'market_cap' => $marketCap,
                    'volume_24h' => $volume24h,
                    'liquidity_pool' => $liquidityUsd,
                    'strategy' => 'WALLET-TRACKED',
                    'dev_sold' => false,
                    'dex_paid_status' => false,
                ]);

                $this->info("Saved SolanaCall ID: {$call->id} - Token: {$tokenName} ({$tokenAddress}) [WALLET BUY]");

                // Trigger buy
                $buyAmount = 0.002;
                SlackNotifier::info("Launching buy for SolanaCall #{$call->id}: {$tokenName} ({$buyAmount} SOL) [WALLET BUY]");

                $process = new Process([
                    'node',
                    base_path('scripts/solana-buy.js'),
                    '--identifier=' . $call->id,
                    '--token=' . $tokenAddress,
                    '--amount=' . $buyAmount,
                ]);
                $process->setTimeout(360);
                $process->run();
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
                } else {
                    SlackNotifier::info("Buy completed for #{$call->id} ({$tokenName}) (no output)");
                }

                $matchesFound++;
            }

            $this->info("Wallet tracker complete: Processed {$matchesFound} tokens.");
            return self::SUCCESS;

        } catch (Throwable $e) {
            $errorMsg = "Error tracking wallets: " . $e->getMessage();
            $this->error($errorMsg);
            SlackNotifier::error($errorMsg);
            return self::FAILURE;
        }
    }

    /**
     * Poll the most recent transaction for a wallet using QuickNode RPC and extract meme coin buy within the last minute.
     *
     * @param string $wallet
     * @return array|null
     */
    private function pollLatestWalletToken(string $wallet): ?array
    {
        $quickNodeUrl = config('services.quicknode.endpoint');

        // Step 1: Get the most recent transaction signature with retry
        $signaturesResponse = Http::retry(3, 100)->post($quickNodeUrl, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getSignaturesForAddress',
            'params' => [
                $wallet,
                [
                    'limit' => 1, // Only the most recent transaction
                ],
            ],
        ]);

        // Debug: Log the signatures response for troubleshooting
        // Remove this after confirming the issue is resolved
        var_dump("Signatures Response:", $signaturesResponse->json());

        if ($signaturesResponse->failed() || !isset($signaturesResponse->json()['result'][0])) {
            $this->warn("Failed to fetch recent signatures for wallet {$wallet}: " . json_encode($signaturesResponse->json()));
            return null;
        }

        $latestSignature = $signaturesResponse->json()['result'][0]['signature'];
        $txBlockTime = $signaturesResponse->json()['result'][0]['blockTime'];

        $recentTime = Carbon::now()->subSeconds(60); // Last 60 seconds
        if (!isset($txBlockTime) || Carbon::createFromTimestamp($txBlockTime)->lt($recentTime)) {
            return null; // Skip if older than 1 minute
        }

        // Step 2: Fetch transaction details with retry using getTransaction
        $txResponse = Http::retry(3, 100)->post($quickNodeUrl, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getTransaction',
            'params' => [
                $latestSignature,
                [
                    'encoding' => 'json', // Use json instead of jsonParsed
                    'maxSupportedTransactionVersion' => 0, // Support legacy transactions
                ],
            ],
        ]);

        // Debug: Log the transaction response for troubleshooting
        // Remove this after confirming the issue is resolved
        var_dump("Transaction Response:", $txResponse->json());

        if ($txResponse->failed() || !isset($txResponse->json()['result'])) {
            $this->warn("Failed to fetch transaction details for signature {$latestSignature}: " . json_encode($txResponse->json()));
            return null;
        }

        $tx = $txResponse->json()['result'];

        // Step 3: Extract token buys
        $tokenInstructions = $this->extractTokenBuysFromTx($tx, $wallet);
        if (empty($tokenInstructions)) {
            $this->info("No token buys detected for wallet {$wallet} in transaction {$latestSignature}");
        }

        foreach ($tokenInstructions as $tokenAddress) {
            if (!$this->isMemeCoin($tokenAddress)) {
                $this->info("Token {$tokenAddress} is not a meme coin (market cap >= 1M or liquidity <= 1000)");
                continue;
            }

            // Avoid buying the top with checkMarketMetrics
            $this->tokenAddress = $tokenAddress;
            if (!$this->checkMarketMetrics()) {
                $this->warn("Skipping {$tokenAddress}: Failed market metrics check");
                continue;
            }

            $info = $this->getTokenInfo($tokenAddress, 'solana');
            return [
                'tokenAddress' => $tokenAddress,
                'chainId' => 'solana',
                'boosted' => false,
                'walletBuy' => true,
                'tokenName' => substr($info['name'] ?? 'Unknown Token', 0, 100),
                'ticker' => $info['symbol'] ?? null,
                'extra' => ['txSignature' => $latestSignature],
            ];
        }

        return null;
    }

    /**
     * Extract token mint addresses from a QuickNode getTransaction response where the wallet bought tokens.
     *
     * @param array $tx
     * @param string $wallet
     * @return array
     */
    private function extractTokenBuysFromTx(array $tx, string $wallet): array
    {
        $tokenAddresses = [];

        // Check pre/post token balances for increases (buys)
        $preTokenBalances = $tx['meta']['preTokenBalances'] ?? [];
        $postTokenBalances = $tx['meta']['postTokenBalances'] ?? [];

        // Check if wallet has any token balance changes
        $walletHasTokenChanges = false;
        foreach ($postTokenBalances as $balance) {
            if ($balance['owner'] === $wallet) {
                $walletHasTokenChanges = true;
                break;
            }
        }
        if (!$walletHasTokenChanges) {
            $this->info("No token balance changes for wallet {$wallet} in transaction");
            return [];
        }

        foreach ($postTokenBalances as $postBalance) {
            if ($postBalance['owner'] !== $wallet) continue; // Only wallet-owned accounts

            $mint = $postBalance['mint'];
            if ($mint === 'So11111111111111111111111111111111111111112') continue; // Skip SOL/WSOL

            // Find matching pre-balance
            $preBalance = collect($preTokenBalances)->firstWhere('mint', $mint) ?? ['uiTokenAmount' => ['uiAmount' => 0]];
            $preAmount = $preBalance['uiTokenAmount']['uiAmount'] ?? 0;
            $postAmount = $postBalance['uiTokenAmount']['uiAmount'] ?? 0;

            if ($postAmount > $preAmount) {
                $this->info("Detected buy of token {$mint} for wallet {$wallet}: {$preAmount} -> {$postAmount}");
                $tokenAddresses[] = $mint;
            }
        }

        // Fallback: Check instructions for Jupiter swaps
        $innerInstructions = $tx['meta']['innerInstructions'] ?? [];
        foreach ($innerInstructions as $inner) {
            foreach ($inner['instructions'] as $instr) {
                if (isset($instr['programId']) && strpos($instr['programId'], 'JUP') !== false) { // Jupiter program
                    $mint = $this->parseJupiterMintFromInstruction($instr);
                    if ($mint && $this->isJupiterBuy($instr, $wallet)) {
                        $this->info("Detected Jupiter buy of token {$mint} for wallet {$wallet}");
                        $tokenAddresses[] = $mint;
                    }
                }
            }
        }

        return array_unique($tokenAddresses);
    }

    /**
     * Determine if a Jupiter instruction is a buy (wallet receiving tokens).
     *
     * @param array $instruction
     * @param string $wallet
     * @return bool
     */
    private function isJupiterBuy(array $instruction, string $wallet): bool
    {
        // getTransaction instructions are less structured, so we rely on parsed data if available
        $parsed = $instruction['parsed'] ?? [];
        $destination = $parsed['info']['destination'] ?? null;
        $mint = $parsed['info']['mint'] ?? null;
        $amount = (int)($parsed['info']['tokenAmount'] ?? 0);

        return $destination === $wallet && $mint !== 'So11111111111111111111111111111111111111112' && $amount > 0;
    }

    /**
     * Parse mint from Jupiter swap instruction in getTransaction response.
     *
     * @param array $instruction
     * @return string|null
     */
    private function parseJupiterMintFromInstruction(array $instruction): ?string
    {
        // getTransaction may not always parse 'mint', so check raw instruction data
        return $instruction['parsed']['info']['mint'] ?? null;
    }

    /**
     * Check if a token is a meme coin (low market cap, decent liquidity).
     *
     * @param string $tokenAddress
     * @return bool
     */
    private function isMemeCoin(string $tokenAddress): bool
    {
        $data = (new SolanaTokenData())->getTokenData($tokenAddress);
        if (!$data) return false;

        $marketCap = $data['marketCap'] ?? 0;
        $liquidity = $data['liquidity']['usd'] ?? 0;

        return $marketCap > 0 && $marketCap < 1000000 && $liquidity > 1000;
    }

    /**
     * Check if token should be skipped (already traded recently).
     *
     * @param string $tokenAddress
     * @return bool
     */
    private function shouldSkipToken(string $tokenAddress): bool
    {
        $call = SolanaCall::where('token_address', $tokenAddress)
            ->latest('id')
            ->with('orders')
            ->first();

        if (!$call) return false;
        $orders = $call->orders;
        if ($orders->isEmpty()) return true;

        $hasBuy = $orders->where('type', 'buy')->count() > 0;
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

    /**
     * Check market metrics to avoid buying tops.
     *
     * @return bool
     */
    protected function checkMarketMetrics(): bool
    {
        $tokenData = (new SolanaTokenData())->getTokenData($this->tokenAddress);
        if ($tokenData === null) {
            $this->warn("Skipping {$this->tokenAddress}: Failed to fetch token data from QuickNode");
            return false;
        }

        $marketCap = $tokenData['marketCap'] ?? 0;
        $liquidity = $tokenData['liquidity']['usd'] ?? 0;
        $volumeH1 = $tokenData['volume']['h1'] ?? 0;
        $priceChangeM5 = $tokenData['priceChange']['m5'] ?? 0;
        $priceChangeH1 = $tokenData['priceChange']['h1'] ?? 0;
        $priceChangeH6 = $tokenData['priceChange']['h6'] ?? 0;
        $priceChangeH24 = $tokenData['priceChange']['h24'] ?? 0;

        $minLiquidity = 10000;
        $minMarketCap = 5000;
        $maxMarketCap = 50000000;
        $minVolumeH1 = 2000;
        $minVolLiqRatio = 0.5;
        $minM5Gain = 0.5;
        $maxM5Gain = 50;
        $minH1Gain = -10;
        $maxH1Gain = 50;
        $minH6Gain = 5;
        $rugThreshold = -50;

        $allDrops = [$priceChangeM5, $priceChangeH1, $priceChangeH6, $priceChangeH24];
        foreach ($allDrops as $drop) {
            if (!is_numeric($drop) || $drop <= $rugThreshold) {
                $this->warn("Skipping {$this->tokenAddress}: potential rug detected ({$drop}% change <= {$rugThreshold}% or invalid)");
                return false;
            }
        }

        if (!is_numeric($liquidity) || $liquidity < $minLiquidity) {
            $this->info("Skipping {$this->tokenAddress}: liquidity \${$liquidity} < \${$minLiquidity} or invalid");
            return false;
        }

        if (!is_numeric($marketCap) || $marketCap < $minMarketCap || $marketCap > $maxMarketCap) {
            $this->info("Skipping {$this->tokenAddress}: marketCap \${$marketCap} outside range \${$minMarketCap}-\${$maxMarketCap} or invalid");
            return false;
        }

        $volLiqRatio = ($liquidity > 0) ? ($volumeH1 / $liquidity) : 0;
        if (!is_numeric($volumeH1) || $volumeH1 < $minVolumeH1 || $volLiqRatio < $minVolLiqRatio) {
            $this->info("Skipping {$this->tokenAddress}: volumeH1 \${$volumeH1}, vol/liq ratio={$volLiqRatio} below threshold {$minVolLiqRatio} or invalid");
            return false;
        }

        if (!is_numeric($priceChangeH1) || $priceChangeH1 < $minH1Gain || $priceChangeH1 > $maxH1Gain) {
            $this->info("Skipping {$this->tokenAddress}: H1 change {$priceChangeH1}% outside range {$minH1Gain}% to {$maxH1Gain}% or invalid");
            return false;
        }

        if (!is_numeric($priceChangeM5) || $priceChangeM5 < $minM5Gain || $priceChangeM5 > $maxM5Gain) {
            $this->info("Skipping {$this->tokenAddress}: M5 change {$priceChangeM5}% outside range {$minM5Gain}% to {$maxM5Gain}% or invalid");
            return false;
        }

        if (!is_numeric($priceChangeH6) || $priceChangeH6 < $minH6Gain) {
            $this->info("Skipping {$this->tokenAddress}: H6 change {$priceChangeH6}% < {$minH6Gain}% or invalid");
            return false;
        }

        return true;
    }
}
