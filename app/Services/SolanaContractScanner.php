<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SolanaContractScanner
{
    protected string $tokenAddress;
    protected string $chain;
    protected bool $isBoosted = false;
    protected bool $trimmedChecks = false;

    // Store the DexScreener pair data to avoid multiple API calls
    protected array $pairData = [];

    public function __construct(string $tokenAddress, string $chain = 'solana', $trimmedChecks = false)
    {
        $this->tokenAddress = $tokenAddress;
        $this->chain = $chain;
        $this->trimmedChecks = $trimmedChecks;

        // Fetch and cache the pair data on initialization
        $this->fetchPairData();
    }

    public function setBoosted(bool $boosted): void
    {
        $this->isBoosted = $boosted;
    }

    public function canTrade(array $tokenData): bool
    {
        if ($this->trimmedChecks) {
            $checks = ['checkMarketMetrics'];
        } else {
            $checks = ['checkMarketMetrics', 'checkRugProof', 'checkBirdseye', 'checkSocials'];
        }

        foreach ($checks as $check) {
            try {
                if ($check === 'checkMarketMetrics') {
                    if (!$this->$check($tokenData)) {
                        Log::info("❌ {$check} failed for {$this->tokenAddress}");
                        return false;
                    }
                } else {
                    if (!$this->$check()) {
                        Log::info("❌ {$check} failed for {$this->tokenAddress}");
                        return false;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("⚠️ {$check} exception for {$this->tokenAddress}: {$e->getMessage()}");
                return false;
            }
        }

        return true; // all checks passed
    }

    // ---------------- PRIVATE CHECKS ---------------- //

    protected function fetchPairData(): void
    {
        try {
            $res = Http::get("https://api.dexscreener.com/token-pairs/v1/{$this->chain}/{$this->tokenAddress}");
            if ($res->successful()) {
                $this->pairData = $res->json();
            } else {
                $this->pairData = [];
                Log::info("Failed to fetch pair data for {$this->tokenAddress}");
            }
        } catch (Throwable $e) {
            $this->pairData = [];
            Log::warning("Exception fetching pair data for {$this->tokenAddress}: {$e->getMessage()}");
        }
    }

    public function checkMarketMetrics($tokenData): bool
    {
        // Extract metrics with null coalescing for safety
        $marketCap     = $tokenData['marketCap'] ?? 0;
        $liquidity     = $tokenData['liquidity']['usd'] ?? 0;
        $volumeH1      = $tokenData['volume']['h1'] ?? 0;
        $priceChangeM5 = $this->pairData[0]['priceChange']['m5'] ?? 0;
        $priceChangeH1 = $tokenData['priceChange']['h1'] ?? 0;
        $priceChangeH6 = $tokenData['priceChange']['h6'] ?? 0;
        $priceChangeH24 = $tokenData['priceChange']['h24'] ?? 0;

        // --- Thresholds ---
        $minLiquidity   = 5000;          // Increased to ensure more robust liquidity
        $minMarketCap   = 5000;          // Slightly higher to avoid micro-caps
        $maxMarketCap   = 50000000;      // Increased to capture more growth potential
        $minVolumeH1    = 1000;          // Higher volume for stronger trading activity
        $minVolLiqRatio = 0.3;           // Slightly higher to ensure active trading
        $minM5Gain      = 0.5;           // Require stronger short-term momentum
        $maxM5Gain      = 100;           // Allow larger pumps for new listings
        $maxH1Loss      = -15;           // Allow slightly larger 1h dips
        $minH6Gain      = 0;             // New: Ensure non-negative 6h trend
        $rugThreshold   = -50;           // Unchanged: Reject extreme drops

        // --- Rug filter: reject if any timeframe has extreme drop ---
        $allDrops = [$priceChangeM5, $priceChangeH1, $priceChangeH6, $priceChangeH24];
        foreach ($allDrops as $drop) {
            if (!is_numeric($drop) || $drop <= $rugThreshold) {
                Log::warning("Skipping {$this->tokenAddress}: potential rug detected ({$drop}% change <= {$rugThreshold}% or invalid)");
                return false;
            }
        }

        // --- Liquidity check ---
        if (!is_numeric($liquidity) || $liquidity < $minLiquidity) {
            Log::info("Skipping {$this->tokenAddress}: liquidity \${$liquidity} < \${$minLiquidity} or invalid");
            return false;
        }

        // --- Market cap check ---
        if (!is_numeric($marketCap) || $marketCap < $minMarketCap || $marketCap > $maxMarketCap) {
            Log::info("Skipping {$this->tokenAddress}: marketCap \${$marketCap} outside range \${$minMarketCap}-\${$maxMarketCap} or invalid");
            return false;
        }

        // --- Volume and volume-to-liquidity ratio check ---
        $volLiqRatio = ($liquidity > 0) ? ($volumeH1 / $liquidity) : 0;
        if (!is_numeric($volumeH1) || $volumeH1 < $minVolumeH1 || $volLiqRatio < $minVolLiqRatio) {
            Log::info("Skipping {$this->tokenAddress}: volumeH1 \${$volumeH1}, vol/liq ratio={$volLiqRatio} below threshold {$minVolLiqRatio} or invalid");
            return false;
        }

        // --- Price trend checks ---
        // Allow tokens with positive or slightly negative 1-hour trend
        if (!is_numeric($priceChangeH1) || $priceChangeH1 < $maxH1Loss) {
            Log::info("Skipping {$this->tokenAddress}: H1 change {$priceChangeH1}% < {$maxH1Loss}% or invalid");
            return false;
        }

        // Ensure positive 5-minute momentum
        if (!is_numeric($priceChangeM5) || $priceChangeM5 < $minM5Gain || $priceChangeM5 > $maxM5Gain) {
            Log::info("Skipping {$this->tokenAddress}: M5 change {$priceChangeM5}% outside desired range ({$minM5Gain}% → {$maxM5Gain}%) or invalid");
            return false;
        }

        // New: Ensure 6-hour trend is non-negative to confirm longer-term stability
        if (!is_numeric($priceChangeH6) || $priceChangeH6 < $minH6Gain) {
            Log::info("Skipping {$this->tokenAddress}: H6 change {$priceChangeH6}% < {$minH6Gain}% or invalid");
            return false;
        }

        // Optional: Log successful checks
        Log::info("✅ Market metrics passed for {$this->tokenAddress}: MC=\${$marketCap}, Liq=\${$liquidity}, Vol=\${$volumeH1}, M5={$priceChangeM5}%, H1={$priceChangeH1}%, H6={$priceChangeH6}%");

        return true;
    }

    private function checkRugProof(): bool
    {
        $rugCheck = Http::get("https://api.rugcheck.xyz/v1/tokens/{$this->tokenAddress}/report")->json();
        $riskScore = $rugCheck['score_normalised'] ?? 100;
        $rugged = $rugCheck['rugged'];

        if ($riskScore >= 50) return false;
        if ($rugged) return false;
        return true;
    }

    private function checkBirdseye(): bool
    {
        $holdersResponse = Http::withHeaders([
            'X-API-KEY' => env('BIRDEYE_API_KEY')
        ])->get("https://public-api.birdeye.so/defi/v3/token/holder?address={$this->tokenAddress}&limit=50");

        if (!$holdersResponse->json('success')) {
            // most likely "Compute units usage limit exceeded", go degen mode and skip this check
            return true;
        }

        $holdersData = $holdersResponse->json();
        $items = $holdersData['data']['items'] ?? [];
        $holderCount = count($items);

        if ($holderCount < 50) {
            return false;
        }

        $overviewResponse = Http::withHeaders([
            'X-API-KEY' => env('BIRDEYE_API_KEY')
        ])->get("https://public-api.birdeye.so/defi/token/overview?address={$this->tokenAddress}");

        if ($overviewResponse->failed()) {
            $pairs = $this->pairData;
            $marketCap = $pairs[0]['marketCap'] ?? 0;
            $priceUsd = $pairs[0]['priceUsd'] ?? 0;
            $totalSupply = ($priceUsd > 0) ? $marketCap / $priceUsd : 0;
        } else {
            $overviewData = $overviewResponse->json();
            $totalSupply = $overviewData['data']['supply'] ?? 0;
        }

        if ($totalSupply <= 0) {
            return false;
        }

        $topHolderAmount = $items[0]['ui_amount'] ?? 0;
        $topHolderPct = ($topHolderAmount / $totalSupply) * 100;
        $maxTopHolderPct = $this->isBoosted ? 30.0 : 20.0;

        if ($topHolderPct > $maxTopHolderPct) {
            return false;
        }

        return true;
    }


    private function checkSocials(): bool
    {
        $pairs = $this->pairData;
        if (empty($pairs) || !is_array($pairs)) return false;

        $allSocials = [];
        foreach ($pairs as $pair) {
            $socials = $pair['info']['socials'] ?? [];
            foreach ($socials as $social) {
                $url = $social['url'] ?? null;
                if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                    $allSocials[$url] = true;
                }
            }
        }

        return count($allSocials) >= 1;
    }
}
