<?php

namespace App\Services;

use App\Helpers\SolanaTokenData;
use App\Models\SolanaBlacklistContract;
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

    protected $tokenData;

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

    public function getPairData(): array
    {
        return $this->pairData;
    }

    public function getTokenData()
    {
        return $this->tokenData;
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
        $this->tokenData = $this->tokenDataHelper->getTokenData($this->tokenAddress);

        if ($this->tokenData === null) {
            return false;
        }

        // Extract metrics with null coalescing for safety
        $marketCap      = $this->tokenData['marketCap'] ?? 0;
        $liquidity      = $this->tokenData['liquidity']['usd'] ?? 0;
        $volumeH1       = $this->tokenData['volume']['h1'] ?? 0;
        $priceChangeM5  = $this->tokenData['priceChange']['m5'] ?? 0;
        $priceChangeH1  = $this->tokenData['priceChange']['h1'] ?? 0;
        $priceChangeH6  = $this->tokenData['priceChange']['h6'] ?? 0;
        $priceChangeH24 = $this->tokenData['priceChange']['h24'] ?? 0;

        // --- Thresholds (refined) ---
        $minLiquidity   = 20000;        // Safer liquidity floor
        $minMarketCap   = 10000;        // Avoid micro-caps
        $maxMarketCap   = 20000000;     // Capture growth potential, avoid overheated
        $minVolumeH1    = 5000;         // Require stronger trading activity
        $minVolLiqRatio = 0.5;          // Volume/liquidity ratio for active trading
        $minM5Gain      = 1;            // Require at least +1% in 5m
        $maxM5Gain      = 8;            // Avoid extreme short-term pumps
        $minH1Gain      = -5;           // Allow mild dips in 1h
        $maxH1Gain      = 20;           // Avoid overheated 1h spikes
        $minH6Gain      = 3;            // Require healthy 6h trend
        $rugThreshold   = -40;          // Reject tokens with extreme drops

        // --- Rug filter: reject if any timeframe has extreme drop ---
        $allDrops = [$priceChangeM5, $priceChangeH1, $priceChangeH6, $priceChangeH24];
        foreach ($allDrops as $drop) {
            if (!is_numeric($drop) || $drop <= $rugThreshold) {
                return false;
            }
        }

        // --- Liquidity check ---
        if (!is_numeric($liquidity) || $liquidity < $minLiquidity) {
            return false;
        }

        // --- Market cap check ---
        if (!is_numeric($marketCap) || $marketCap < $minMarketCap || $marketCap > $maxMarketCap) {
            return false;
        }

        // --- Volume and volume-to-liquidity ratio check ---
        $volLiqRatio = ($liquidity > 0) ? ($volumeH1 / $liquidity) : 0;
        if (!is_numeric($volumeH1) || $volumeH1 < $minVolumeH1 || $volLiqRatio < $minVolLiqRatio) {
            return false;
        }

        // --- Price trend checks ---
        // Ensure 1h trend is within bounds
        if (!is_numeric($priceChangeH1) || $priceChangeH1 < $minH1Gain || $priceChangeH1 > $maxH1Gain) {
            return false;
        }

        // Ensure positive but controlled 5-minute momentum
        if (!is_numeric($priceChangeM5) || $priceChangeM5 < $minM5Gain || $priceChangeM5 > $maxM5Gain) {
            return false;
        }

        // Consistency check: reject if 5m is up but 1h is still negative
        if ($priceChangeM5 > 0 && $priceChangeH1 < 0) {
            return false;
        }

        // Ensure healthy 6-hour trend for stability
        if (!is_numeric($priceChangeH6) || $priceChangeH6 < $minH6Gain) {
            return false;
        }

        // Optional: reject fake pumps (big 5m change with weak volume)
        if ($priceChangeM5 > 2 && $volumeH1 < ($liquidity * 0.8)) {
            return false;
        }

        return true;
    }

    private function checkRugProof(): bool
    {
        $rugCheck = Http::get("https://api.rugcheck.xyz/v1/tokens/{$this->tokenAddress}/report")->json();
        $riskScore = $rugCheck['score_normalised'] ?? 100;
        $rugged = $rugCheck['rugged'] ?? false;

        if ($riskScore >= 50 || $rugged) {
            // Blacklist the token if it fails the rug check
            SolanaBlacklistContract::create(['contract' => $this->tokenAddress]);
            Log::info("Added {$this->tokenAddress} to solana_blacklist_contracts due to failed rug check (Risk Score: {$riskScore}, Rugged: {$rugged})");
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
