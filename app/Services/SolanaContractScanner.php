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
//            'checkMarketMetrics',
//            'checkRugProof',
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
        $volume = $tokenData['volume']['h24'] ?? 0;
        $priceChange = $tokenData['priceChange']['h24'] ?? 0;

        // Relaxed thresholds
        $minLiquidity = max(500, $volume * 0.05); // 5% of 24h volume or at least $500
        $maxMarketCap = max(5_000_000, $marketCap * 2);
        $minVolumeGrowth = 200;
        $maxPriceDrop = -80;  // allow more volatility
        $maxPriceRise = 500;

        if ($liquidity < $minLiquidity) return false;
        if ($marketCap > $maxMarketCap) return false;
        if ($volume < $minVolumeGrowth) return false;
        if ($priceChange < $maxPriceDrop || $priceChange > $maxPriceRise) return false;

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
