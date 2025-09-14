<?php

namespace App\Services;

use App\Helpers\SolanaTokenData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SolanaContractScanner
{
    protected string $tokenAddress;
    protected string $chain;
    protected bool $isBoosted = false;
    protected bool $trimmedChecks = false;
    protected array $pairData = [];
    protected SolanaTokenData $tokenDataHelper;

    public function __construct(string $tokenAddress, string $chain = 'solana', $trimmedChecks = false)
    {
        $this->tokenAddress = $tokenAddress;
        $this->chain = $chain;
        $this->trimmedChecks = $trimmedChecks;
        $this->tokenDataHelper = new SolanaTokenData();

        // Fetch pair data for socials
        $this->fetchPairData();
    }

    public function setBoosted(bool $boosted): void
    {
        $this->isBoosted = $boosted;
    }

    public function canTrade(): bool
    {
        if ($this->trimmedChecks) {
            $checks = ['checkMarketMetrics'];
        } else {
            $checks = ['checkMarketMetrics', 'checkRugProof', 'checkSocials'];
        }

        foreach ($checks as $check) {
            try {
                if (!$this->$check()) {
                    Log::info("❌ {$check} failed for {$this->tokenAddress}");
                    return false;
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

    protected function checkMarketMetrics(): bool
    {
        // Fetch token data using SolanaTokenData helper
        $tokenData = $this->tokenDataHelper->getTokenData($this->tokenAddress);

        if ($tokenData === null) {
            $this->warn("Skipping {$this->tokenAddress}: Failed to fetch token data from QuickNode");
            return false;
        }

        // Extract metrics with null coalescing for safety
        $marketCap = $tokenData['marketCap'] ?? 0;
        $liquidity = $tokenData['liquidity']['usd'] ?? 0;
        $volumeH1 = $tokenData['volume']['h1'] ?? 0;
        $priceChangeM5 = $tokenData['priceChange']['m5'] ?? 0;
        $priceChangeH1 = $tokenData['priceChange']['h1'] ?? 0;
        $priceChangeH6 = $tokenData['priceChange']['h6'] ?? 0;
        $priceChangeH24 = $tokenData['priceChange']['h24'] ?? 0;

        // --- Thresholds ---
        $minLiquidity = 10000;         // Increased to ensure robust liquidity
        $minMarketCap = 5000;          // Avoid micro-caps
        $maxMarketCap = 50000000;      // Capture growth potential
        $minVolumeH1 = 2000;           // Stronger trading activity
        $minVolLiqRatio = 0.5;         // Higher ratio for active trading
        $minM5Gain = 0.5;              // Positive short-term momentum
        $maxM5Gain = 50;               // Tightened to avoid extreme pumps
        $minH1Gain = -10;              // Allow smaller dips
        $maxH1Gain = 50;               // New: Avoid large 1h pumps
        $minH6Gain = 5;                // Require positive 6h trend
        $rugThreshold = -50;           // Reject extreme drops

        // --- Rug filter: reject if any timeframe has extreme drop ---
        $allDrops = [$priceChangeM5, $priceChangeH1, $priceChangeH6, $priceChangeH24];
        foreach ($allDrops as $drop) {
            if (!is_numeric($drop) || $drop <= $rugThreshold) {
                $this->warn("Skipping {$this->tokenAddress}: potential rug detected ({$drop}% change <= {$rugThreshold}% or invalid)");
                return false;
            }
        }

        // --- Liquidity check ---
        if (!is_numeric($liquidity) || $liquidity < $minLiquidity) {
            $this->info("Skipping {$this->tokenAddress}: liquidity \${$liquidity} < \${$minLiquidity} or invalid");
            return false;
        }

        // --- Market cap check ---
        if (!is_numeric($marketCap) || $marketCap < $minMarketCap || $marketCap > $maxMarketCap) {
            $this->info("Skipping {$this->tokenAddress}: marketCap \${$marketCap} outside range \${$minMarketCap}-\${$maxMarketCap} or invalid");
            return false;
        }

        // --- Volume and volume-to-liquidity ratio check ---
        $volLiqRatio = ($liquidity > 0) ? ($volumeH1 / $liquidity) : 0;
        if (!is_numeric($volumeH1) || $volumeH1 < $minVolumeH1 || $volLiqRatio < $minVolLiqRatio) {
            $this->info("Skipping {$this->tokenAddress}: volumeH1 \${$volumeH1}, vol/liq ratio={$volLiqRatio} below threshold {$minVolLiqRatio} or invalid");
            return false;
        }

        // --- Price trend checks ---
        // Avoid tokens with large 1-hour pumps or significant losses
        if (!is_numeric($priceChangeH1) || $priceChangeH1 < $minH1Gain || $priceChangeH1 > $maxH1Gain) {
            $this->info("Skipping {$this->tokenAddress}: H1 change {$priceChangeH1}% outside range {$minH1Gain}% to {$maxH1Gain}% or invalid");
            return false;
        }

        // Ensure positive 5-minute momentum, avoid extreme pumps
        if (!is_numeric($priceChangeM5) || $priceChangeM5 < $minM5Gain || $priceChangeM5 > $maxM5Gain) {
            $this->info("Skipping {$this->tokenAddress}: M5 change {$priceChangeM5}% outside range {$minM5Gain}% to {$maxM5Gain}% or invalid");
            return false;
        }

        // Ensure positive 6-hour trend for longer-term stability
        if (!is_numeric($priceChangeH6) || $priceChangeH6 < $minH6Gain) {
            $this->info("Skipping {$this->tokenAddress}: H6 change {$priceChangeH6}% < {$minH6Gain}% or invalid");
            return false;
        }

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
