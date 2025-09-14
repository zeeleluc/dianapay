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
        $lpBurned = $rugCheck['lpLockedPct'] ?? 0;

        if ($riskScore >= 70) return false; // relaxed
        if ($lpBurned < 30) return false;    // relaxed LP requirement

        return true;
    }

    private function checkBirdseye(): bool
    {
        $holdersResponse = Http::withHeaders([
            'X-API-KEY' => env('BIRDEYE_API_KEY')
        ])->get("https://public-api.birdeye.so/defi/v3/token/holder?address={$this->tokenAddress}&limit=50");

        if ($holdersResponse->failed()) return false; // fail if API fails

        $holdersData = $holdersResponse->json();
        $items = $holdersData['data']['items'] ?? [];
        $holderCount = count($items);

        return $holderCount >= 20; // lower threshold but still strict
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

        return count($allSocials) >= 1; // require at least 1 social link
    }

}
