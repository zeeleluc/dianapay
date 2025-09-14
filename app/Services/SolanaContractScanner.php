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

        if (!$this->isBoosted) {
            try {
                if (!$this->checkFreshness()) {
                    Log::info("❌ checkFreshness failed for {$this->tokenAddress}");
                    return false;
                }
            } catch (\Throwable $e) {
                Log::warning("⚠️ checkFreshness exception for {$this->tokenAddress}: {$e->getMessage()}");
                return false;
            }
        }

        return true;
    }

    // ---------------- PRIVATE CHECKS ---------------- //

    private function checkFreshness(): bool
    {
        $pairResponse = Http::get("https://api.dexscreener.com/token-pairs/v1/{$this->chain}/{$this->tokenAddress}");
        $pairs = $pairResponse->json();
        if (empty($pairs) || !is_array($pairs)) return false;

        $pair = $pairs[0];
        $pairCreatedAtMs = $pair['pairCreatedAt'] ?? 0;
        if ($pairCreatedAtMs <= 0) return false;

        $ageMinutes = max(0, round((time() - ($pairCreatedAtMs / 1000)) / 60));
        return $ageMinutes <= 5; // keep same cutoff
    }

    public function checkMarketMetrics($tokenData)
    {
        $marketCap = $tokenData['marketCap'] ?? 0;
        $liquidity = $tokenData['liquidity']['usd'] ?? 0;    // fix key
        $volume = $tokenData['volume']['h24'] ?? 0;           // fix key
        $priceChange = $tokenData['priceChange']['h24'] ?? 0; // fix key

        // Dynamic thresholds
        $minLiquidity = max(1000, $volume * 0.1); // 10% of 24h volume or at least 1k USD
        $maxMarketCap = max(1000000, $marketCap * 2);
        $minVolumeGrowth = 500;
        $maxPriceDrop = -50;
        $maxPriceRise = 500;

        if ($liquidity < $minLiquidity) {
            \Log::info("Liquidity too low for {$tokenData['baseToken']['address']}: {$liquidity}");
            return false;
        }

        if ($marketCap > $maxMarketCap) {
            \Log::info("MarketCap out of range for {$tokenData['baseToken']['address']}: {$marketCap}");
            return false;
        }

        if ($volume < $minVolumeGrowth) {
            \Log::info("24h volume too low for {$tokenData['baseToken']['address']}: {$volume}");
            return false;
        }

        if ($priceChange < $maxPriceDrop || $priceChange > $maxPriceRise) {
            \Log::info("24h price change out of range for {$tokenData['baseToken']['address']}: {$priceChange}%");
            return false;
        }

        return true;
    }

    private function checkRugProof(): bool
    {
        $rugCheck = Http::get("https://api.rugcheck.xyz/v1/tokens/{$this->tokenAddress}/report")->json();

        $riskScore = $rugCheck['score_normalised'] ?? 100;
        $lpBurned = $rugCheck['lpLockedPct'] ?? 0;

        // Reject if normalized score too high
        if ($riskScore >= 50) {
            Log::info("RugCheck score too high: {$riskScore}");
            return false;
        }

        // Reject if LP not burned or not locked
        if ($lpBurned < 50) { // e.g., require at least 50% LP locked
            Log::info("Liquidity pool insufficiently locked: {$lpBurned}%");
            return false;
        }

        // Reject if creator has a history of dangerous tokens
        foreach ($rugCheck['creatorTokens'] ?? [] as $creatorToken) {
            if ($creatorToken['marketCap'] < 1000) { // tiny tokens often risky
                Log::info("Creator history risky: {$creatorToken['mint']}");
                return false;
            }
        }

        // Reject if token too centralized
        if (($rugCheck['totalHolders'] ?? 0) < 50) {
            Log::info("Not enough holders: {$rugCheck['totalHolders']}");
            return false;
        }

        // Reject if any 'danger' risks in the report
        foreach ($rugCheck['risks'] ?? [] as $risk) {
            if ($risk['level'] === 'danger') {
                Log::info("Dangerous risk detected: {$risk['name']}");
                return false;
            }
        }

        // Reject if token is already flagged as rugged
        if (!empty($rugCheck['rugged'])) {
            Log::info("Token flagged as rugged");
            return false;
        }

        return true;
    }

    private function checkBirdseye(): bool
    {
        $holdersResponse = Http::withHeaders([
            'X-API-KEY' => env('BIRDEYE_API_KEY')
        ])->get("https://public-api.birdeye.so/defi/v3/token/holder?address={$this->tokenAddress}&limit=50");

        // Check for 200 OK
        if ($holdersResponse->failed()) {
            Log::warning("Birdseye API failed for token {$this->tokenAddress}: HTTP {$holdersResponse->status()}");
            return false; // fail-safe: assume risky if no data
        }

        $holdersData = $holdersResponse->json();

        // Ensure 'items' exists
        $items = $holdersData['data']['items'] ?? [];
        $holderCount = count($items);

        Log::info("Token {$this->tokenAddress} has {$holderCount} holders according to Birdseye.");

        // Require at least 50 holders
        return $holderCount >= 50;
    }

    private function checkSocials(): bool
    {
        $pairResponse = Http::get("https://api.dexscreener.com/token-pairs/v1/{$this->chain}/{$this->tokenAddress}");

        if ($pairResponse->failed()) {
            Log::warning("Dexscreener API failed for token {$this->tokenAddress}: HTTP {$pairResponse->status()}");
            return false;
        }

        $pairs = $pairResponse->json();

        if (empty($pairs) || !is_array($pairs)) {
            return false;
        }

        $allSocials = [];

        foreach ($pairs as $pair) {
            $socials = $pair['info']['socials'] ?? [];
            foreach ($socials as $social) {
                $url = $social['url'] ?? null;
                if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                    $allSocials[$url] = true; // using keys to avoid duplicates
                }
            }
        }

        $socialCount = count($allSocials);
        Log::info("Token {$this->tokenAddress} has {$socialCount} valid social links.");

        return $socialCount >= 2;
    }
}
