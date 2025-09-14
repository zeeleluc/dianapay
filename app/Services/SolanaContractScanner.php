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

    public function __construct(string $tokenAddress, string $chain = 'solana')
    {
        $this->tokenAddress = $tokenAddress;
        $this->chain = $chain;
    }

    public function setBoosted(bool $boosted): void
    {
        $this->isBoosted = $boosted;
    }

    public function canTrade(array $tokenData): bool
    {
        $checks = [
            'checkMarketMetrics',
            'checkRugProof',
            'checkBirdseye',
            'checkSocials',
        ];

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

        // No freshness check anymore

        return true; // all checks passed
    }

// ---------------- PRIVATE CHECKS ---------------- //

    public function checkMarketMetrics($tokenData)
    {
        $marketCap = $tokenData['marketCap'] ?? 0;
        $liquidity = $tokenData['liquidity']['usd'] ?? 0;
        $volumeH1 = $tokenData['volume']['h1'] ?? 0;
        $priceChangeH1 = $tokenData['priceChange']['h1'] ?? 0;

        // Fetch 5-minute price change
        $pairResponse = Http::get("https://api.dexscreener.com/token-pairs/v1/{$this->chain}/{$this->tokenAddress}");
        if ($pairResponse->failed()) {
            Log::info("Failed to fetch pair data for {$this->tokenAddress}");
            return false;
        }
        $pairs = $pairResponse->json();
        $priceChangeM5 = $pairs[0]['priceChange']['m5'] ?? 0;

        // Dynamic thresholds for 10% profit potential
        $minLiquidity = $marketCap <= 50000 ? 10000 : 50000; // $10K for low MC, $50K for high MC
        $minMarketCap = 5000; // Avoid microcap scams
        $maxMarketCap = 10000000; // Up to $10M for high MC pumps
        $minVolumeH1 = $marketCap <= 50000 ? 5000 : 50000; // $5K for low MC, $50K for high MC
        $minVolLiqRatio = 1.0; // Strong pump signal
        $minPriceChangeH1 = 5; // +5% h1 for momentum
        $maxPriceChangeH1 = 30; // Cap at +30% to avoid dumps
        $minPriceChangeM5 = -10; // Allow -10% dip for entry
        $maxPriceChangeM5 = 10; // Cap at +10% to avoid overbought

        if ($liquidity < $minLiquidity) {
            Log::info("Low liquidity for {$this->tokenAddress}: \${$liquidity} (<\$" . ($marketCap <= 50000 ? "10K" : "50K") . ")");
            return false;
        }
        if ($marketCap < $minMarketCap || $marketCap > $maxMarketCap) {
            Log::info("Market cap out of range for {$this->tokenAddress}: \${$marketCap} (want \$5K-\$10M)");
            return false;
        }
        if ($volumeH1 < $minVolumeH1 || ($liquidity > 0 && $volumeH1 / $liquidity < $minVolLiqRatio)) {
            Log::info("Low volume or vol/liq for {$this->tokenAddress}: \${$volumeH1}, ratio=" . ($liquidity ? $volumeH1 / $liquidity : 0));
            return false;
        }
        if ($priceChangeH1 < $minPriceChangeH1 || $priceChangeH1 > $maxPriceChangeH1) {
            Log::info("Hourly price change out of range for {$this->tokenAddress}: {$priceChangeH1}% (want +5% to +30%)");
            return false;
        }
        if ($priceChangeM5 < $minPriceChangeM5 || $priceChangeM5 > $maxPriceChangeM5) {
            Log::info("5-min price change out of range for {$this->tokenAddress}: {$priceChangeM5}% (want -10% to +10%)");
            return false;
        }

        Log::info("Market metrics passed for {$this->tokenAddress}: MC=\${$marketCap}, Liq=\${$liquidity}, Vol=\${$volumeH1}, H1 Change={$priceChangeH1}%, M5 Change={$priceChangeM5}%");
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
        // Fetch holders
        $holdersResponse = Http::withHeaders([
            'X-API-KEY' => env('BIRDEYE_API_KEY')
        ])->get("https://public-api.birdeye.so/defi/v3/token/holder?address={$this->tokenAddress}&limit=50");

        if ($holdersResponse->failed()) {
            Log::warning("Birdeye API failed for {$this->tokenAddress}: {$holdersResponse->status()}");
            return false;
        }

        $holdersData = $holdersResponse->json();
        $items = $holdersData['data']['items'] ?? [];
        $holderCount = count($items);
        if ($holderCount < 50) { // Keep your min threshold
            Log::info("Low holder count for {$this->tokenAddress}: {$holderCount} (<50)");
            return false;
        }

        // Fetch total supply
        $overviewResponse = Http::withHeaders([
            'X-API-KEY' => env('BIRDEYE_API_KEY')
        ])->get("https://public-api.birdeye.so/defi/token/overview?address={$this->tokenAddress}");

        if ($overviewResponse->failed()) {
            Log::warning("Birdeye token overview failed for {$this->tokenAddress}: {$overviewResponse->status()}");
            // Fallback: Estimate from DexScreener (used in checkMarketMetrics)
            $pairResponse = Http::get("https://api.dexscreener.com/token-pairs/v1/{$this->chain}/{$this->tokenAddress}");
            if ($pairResponse->failed()) return false;
            $pairs = $pairResponse->json();
            $marketCap = $pairs[0]['marketCap'] ?? 0;
            $priceUsd = $pairs[0]['priceUsd'] ?? 0;
            $totalSupply = ($priceUsd > 0) ? $marketCap / $priceUsd : 0;
        } else {
            $overviewData = $overviewResponse->json();
            $totalSupply = $overviewData['data']['supply'] ?? 0;
        }

        if ($totalSupply <= 0) {
            Log::warning("Invalid total supply for {$this->tokenAddress}: {$totalSupply}");
            return false;
        }

        // Calculate top holder percentage
        $topHolderAmount = $items[0]['ui_amount'] ?? 0;
        $topHolderPct = ($topHolderAmount / $totalSupply) * 100;
        $maxTopHolderPct = $this->isBoosted ? 30.0 : 20.0; // Relaxed if boosted

        if ($topHolderPct > $maxTopHolderPct) {
            Log::info("Top holder % too high for {$this->tokenAddress}: {$topHolderPct}% (max {$maxTopHolderPct}%)");
            return false;
        }

        Log::info("Birdeye check passed for {$this->tokenAddress}: {$holderCount} holders, top holder {$topHolderPct}%");
        return true;
    }

    private function checkSocials(): bool
    {
        $pairResponse = Http::get("https://api.dexscreener.com/token-pairs/v1/{$this->chain}/{$this->tokenAddress}");
        if ($pairResponse->failed()) return false;

        $pairs = $pairResponse->json();
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

        return count($allSocials) >= 2;
    }

}
