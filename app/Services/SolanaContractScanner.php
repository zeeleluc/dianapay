<?php

namespace App\Services;

use App\Helpers\SolanaTokenData;
use App\Models\SolanaBlacklistContract;
use App\Models\SolanaCall;
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

        $this->fetchPairData();
    }

    public function getBuyReason()
    {
        return $this->buyReason;
    }

    public function setBoosted(bool $boosted): void
    {
        $this->isBoosted = $boosted;
    }

    public function canTrade(): bool
    {
        $existingCall = SolanaCall::where('token_address', $this->tokenAddress)
            ->with('orders')
            ->first();

        if ($existingCall && !$existingCall->orders->contains(fn($order) => strtolower($order->type) === 'sell')) {
            Log::info("❌ Cannot trade {$this->tokenAddress} — previous call has no sell order.");
            return false;
        }

        $checks = $this->trimmedChecks ? ['checkMarketMetrics'] : ['checkRugProof', 'checkSocials', 'checkMarketMetrics'];

        foreach ($checks as $check) {
            try {
                if (!$this->$check()) {
                    Log::info("❌ {$check} failed for {$this->tokenAddress}");
                    return false;
                }
            } catch (Throwable $e) {
                Log::warning("⚠️ {$check} exception for {$this->tokenAddress}: {$e->getMessage()}");
                return false;
            }
        }

        return true;
    }

    public function getPairData(): array
    {
        return $this->pairData;
    }

    public function getTokenData()
    {
        return $this->tokenData;
    }

    protected function fetchPairData(): void
    {
        try {
            $res = Http::get("https://api.dexscreener.com/token-pairs/v1/{$this->chain}/{$this->tokenAddress}");
            if ($res->successful()) {
                $pairs = $res->json();
                // Sort pairs by liquidity descending
                $this->pairData = collect($pairs)->sortByDesc('liquidity')->values()->all();
            } else {
                $this->pairData = [];
                Log::info("Failed to fetch pair data for {$this->tokenAddress}");
            }
        } catch (Throwable $e) {
            $this->pairData = [];
            Log::warning("Exception fetching pair data for {$this->tokenAddress}: {$e->getMessage()}");
        }
    }

    protected function logFalse(string $reason): void
    {
        Log::info("Failed: $reason");
        $this->buyReason = $reason;
    }

    public function canTradeWithJlpCheck(): bool
    {
        try {
            $this->tokenData = $this->tokenDataHelper->getTokenData($this->tokenAddress);

            if ($this->tokenData === null) {
                $this->logFalse('Token data is null');
                return false;
            }

            // Check that all required priceChange keys exist
            $requiredKeys = ['m5', 'h1', 'h6'];
            foreach ($requiredKeys as $key) {
                if (!isset($this->tokenData['priceChange'][$key])) {
                    \App\Helpers\SlackNotifier::error(
                        "Missing priceChange key '{$key}' for token {$this->tokenAddress}: " . json_encode($this->tokenData)
                    );
                    $this->logFalse("Missing priceChange key '{$key}'");
                    return false;
                }
            }

            // Now safe to assign
            $priceChangeM5 = $this->tokenData['priceChange']['m5'];
            $priceChangeH1 = $this->tokenData['priceChange']['h1'];
            $priceChangeH6 = $this->tokenData['priceChange']['h6'];

            $minM5Pump = 0.05;
            $maxM5Pump = 2.5;
            $maxDownTrend = -5;

            if ($priceChangeM5 < $minM5Pump) {
                $this->logFalse(sprintf("❌ Not enough 5m momentum: %.2f%% < %.2f%%", $priceChangeM5, $minM5Pump));
                return false;
            }

            if ($priceChangeM5 > $maxM5Pump) {
                $this->logFalse(sprintf("❌ Too overheated already: %.2f%% > %.2f%%", $priceChangeM5, $maxM5Pump));
                return false;
            }

            if ($priceChangeH1 < $maxDownTrend || $priceChangeH6 < $maxDownTrend) {
                $this->logFalse(sprintf("❌ Strong downtrend detected (H1 %.2f%%, H6 %.2f%%)", $priceChangeH1, $priceChangeH6));
                return false;
            }

            $this->buyReason = sprintf(
                "✅ JLP scalp setup | M5: %.2f%% | H1: %.2f%% | H6: %.2f%%",
                $priceChangeM5, $priceChangeH1, $priceChangeH6
            );
            \Illuminate\Support\Facades\Log::info($this->buyReason);

            return true;

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                "⚠️ canTradeWithJlpCheck exception for {$this->tokenAddress}: {$e->getMessage()}"
            );
            return false;
        }
    }

    protected function checkMarketMetrics(): bool
    {
        $this->tokenData = $this->tokenDataHelper->getTokenData($this->tokenAddress);
        if ($this->tokenData === null) return false;

        $marketCap      = $this->tokenData['marketCap'] ?? 0;
        $liquidity      = $this->tokenData['liquidity']['usd'] ?? 0;
        $volumeH1       = $this->tokenData['volume']['h1'] ?? 0;

        // Default price changes if null
        $priceChangeM5  = $this->tokenData['priceChange']['m5'] ?? 0;
        $priceChangeH1  = $this->tokenData['priceChange']['h1'] ?? 0;
        $priceChangeH6  = $this->tokenData['priceChange']['h6'] ?? 0;
        $priceChangeH24 = $this->tokenData['priceChange']['h24'] ?? 0;

        $minLiquidity   = 4000000;
        $maxLiquidity   = 20000000;
        $minMarketCap   = 1000000;
        $maxMarketCap   = 50000000;
        $minVolumeH1    = 20000;
        $rugThreshold   = -20;

        $allDrops = [$priceChangeM5, $priceChangeH1, $priceChangeH6, $priceChangeH24];

        foreach ($allDrops as $drop) {
            if (!is_numeric($drop) || $drop <= $rugThreshold) return false;
        }

        if (!is_numeric($liquidity) || $liquidity < $minLiquidity || $liquidity > $maxLiquidity) return false;
        if (!is_numeric($marketCap) || $marketCap < $minMarketCap || $marketCap > $maxMarketCap) return false;

        $volLiqRatio = ($liquidity > 0) ? ($volumeH1 / $liquidity) : 0;
        $dynamicThreshold = $this->getDynamicVolLiqThreshold($liquidity);
        if ($volLiqRatio < $dynamicThreshold) {
            $this->logFalse(sprintf("❌ Vol/Liq ratio: %.2f < %.2f (dynamic)", $volLiqRatio, $dynamicThreshold));
            return false;
        }

        $positiveThresholds = ['M5'=>5, 'H1'=>10, 'H6'=>15, 'H24'=>25];
        $allUp = 0;
        if ($priceChangeM5 > $positiveThresholds['M5']) $allUp++;
        if ($priceChangeH1 > $positiveThresholds['H1']) $allUp++;
        if ($priceChangeH6 > $positiveThresholds['H6']) $allUp++;
        if ($priceChangeH24 > $positiveThresholds['H24']) $allUp++;
        if ($allUp >= 3) return false;

        $this->buyReason = sprintf(
            "Passed metrics: MarketCap %.0f, Liquidity %.0f, VolumeH1 %.0f, M5 %.2f%%, H1 %.2f%%, H6 %.2f%%",
            human_readable_number($marketCap), human_readable_number($liquidity), $volumeH1,
            $priceChangeM5, $priceChangeH1, $priceChangeH6
        );

        return true;
    }

    private function checkRugProof(): bool
    {
        $rugCheck = Http::get("https://api.rugcheck.xyz/v1/tokens/{$this->tokenAddress}/report")->json();
        $riskScore = $rugCheck['score_normalised'] ?? 100;
        $rugged = $rugCheck['rugged'] ?? false;

        if ($riskScore >= 50 || $rugged) {
            SolanaBlacklistContract::create(['contract' => $this->tokenAddress]);
            Log::info("Added {$this->tokenAddress} to solana_blacklist_contracts due to failed rug check (Risk Score: {$riskScore}, Rugged: {$rugged})");
            return false;
        }

        return true;
    }

    private function checkSocials(): bool
    {
        if (empty($this->pairData) || !is_array($this->pairData)) return false;

        $allSocials = [];
        foreach ($this->pairData as $pair) {
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

    protected function getDynamicVolLiqThreshold(float $liquidity): float
    {
        if ($liquidity < 1_000_000) return 1.0;
        if ($liquidity < 5_000_000) return 0.6;
        if ($liquidity < 20_000_000) return 0.4;
        return 0.2;
    }
}
