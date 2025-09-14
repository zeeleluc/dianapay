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
        $marketCap     = $tokenData['marketCap'] ?? 0;
        $liquidity     = $tokenData['liquidity']['usd'] ?? 0;
        $volumeH1      = $tokenData['volume']['h1'] ?? 0;
        $priceChangeH1 = $tokenData['priceChange']['h1'] ?? 0;

        // Last 5-minute change from pairData (assume already fetched)
        $priceChangeM5 = $this->pairData[0]['priceChange']['m5'] ?? 0;

        // --- Thresholds for short-wave trades ---
        $minLiquidity   = 1000;     // allow smaller pools
        $minMarketCap   = 2000;
        $maxMarketCap   = 20000000;
        $minVolumeH1    = 500;      // smaller but active tokens
        $minVolLiqRatio = 0.2;      // volume/liquidity ratio
        $maxH1Loss      = -10;      // allow up to 10% loss last hour
        $minM5Gain      = 0.1;      // require at least 0.1% gain last 5 minutes
        $maxM5Gain      = 50;       // sanity cap

        // --- Checks with logs ---
        if ($liquidity < $minLiquidity) {
            Log::info("Skipping {$this->tokenAddress}: liquidity \${$liquidity} < \${$minLiquidity}");
            return false;
        }

        if ($marketCap < $minMarketCap || $marketCap > $maxMarketCap) {
            Log::info("Skipping {$this->tokenAddress}: marketCap \${$marketCap} outside range \${$minMarketCap}-\${$maxMarketCap}");
            return false;
        }

        $volLiqRatio = ($liquidity > 0) ? ($volumeH1 / $liquidity) : 0;
        if ($volumeH1 < $minVolumeH1 || $volLiqRatio < $minVolLiqRatio) {
            Log::info("Skipping {$this->tokenAddress}: volumeH1 \${$volumeH1}, vol/liq ratio={$volLiqRatio} below threshold {$minVolLiqRatio}");
            return false;
        }

        if ($priceChangeH1 < $maxH1Loss) {
            Log::info("Skipping {$this->tokenAddress}: H1 change {$priceChangeH1}% outside desired short-wave range ({$maxH1Loss}% → 0%)");
            return false;
        }

        if ($priceChangeM5 < $minM5Gain || $priceChangeM5 > $maxM5Gain) {
            Log::info("Skipping {$this->tokenAddress}: M5 change {$priceChangeM5}% outside desired range ({$minM5Gain}% → {$maxM5Gain}%)");
            return false;
        }

        Log::info("✅ Market metrics passed for {$this->tokenAddress}: MC=\${$marketCap}, Liq=\${$liquidity}, Vol=\${$volumeH1}, H1={$priceChangeH1}%, M5={$priceChangeM5}%");

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
