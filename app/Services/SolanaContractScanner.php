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

    protected ?string $buyReason = null;

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
        $minLiquidity   = 20000;
        $minMarketCap   = 10000;
        $maxMarketCap   = 20000000;
        $minVolumeH1    = 5000;
        $minVolLiqRatio = 0.5;
        $minM5Gain      = 1;
        $maxM5Gain      = 8;
        $minH1Gain      = -5;
        $maxH1Gain      = 20;
        $minH6Gain      = 3;
        $rugThreshold   = -40;

        // --- Rug filter ---
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
        if (!is_numeric($priceChangeH1) || $priceChangeH1 < $minH1Gain || $priceChangeH1 > $maxH1Gain) {
            return false;
        }

        if (!is_numeric($priceChangeM5) || $priceChangeM5 < $minM5Gain || $priceChangeM5 > $maxM5Gain) {
            return false;
        }

        if ($priceChangeM5 > 0 && $priceChangeH1 < 0) {
            return false;
        }

        if (!is_numeric($priceChangeH6) || $priceChangeH6 < $minH6Gain) {
            return false;
        }

        if ($priceChangeM5 > 2 && $volumeH1 < ($liquidity * 0.8)) {
            return false;
        }

        // ✅ Passed all checks → store reason
        $this->buyReason = sprintf(
            "Passed metrics: MarketCap %.0f, Liquidity %.0f, VolumeH1 %.0f, M5 %.2f%%, H1 %.2f%%, H6 %.2f%%",
            $marketCap, $liquidity, $volumeH1, $priceChangeM5, $priceChangeH1, $priceChangeH6
        );

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
