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

        // Fetch pair data for socials
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
        // 🔍 Check if this token already has a call with orders
        $existingCall = SolanaCall::where('token_address', $this->tokenAddress)
            ->with('orders')
            ->first();

        if ($existingCall && !$existingCall->orders->contains(fn($order) => strtolower($order->type) === 'sell')) {
            Log::info("❌ Cannot trade {$this->tokenAddress} — previous call has no sell order.");
            return false;
        }

        if ($this->trimmedChecks) {
            $checks = ['checkMarketMetrics'];
        } else {
            $checks = ['checkRugProof', 'checkSocials', 'checkMarketMetrics'];
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

        return true; // ✅ all checks passed
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

    /**
     * Logs why checkBonkMetrics failed.
     */
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

            // --- Extract values ---
            $priceChangeM5  = $this->tokenData['priceChange']['m5'] ?? 0;
            $priceChangeH1  = $this->tokenData['priceChange']['h1'] ?? 0;
            $priceChangeH6  = $this->tokenData['priceChange']['h6'] ?? 0;

            // --- Pump detection thresholds ---
            $minM5Pump      = 0.1;   // at least +0.05% in 5m
            $maxM5Pump      = 2.5;    // not already overheated (>2.5% in 5m)
            $maxDownTrend   = -5;     // avoid if H1/H6 trend is nuking

            // --- 5m momentum check ---
            if ($priceChangeM5 < $minM5Pump) {
                $this->logFalse(sprintf("❌ Not enough 5m momentum: %.2f%% < %.2f%%", $priceChangeM5, $minM5Pump));
                return false;
            }

            if ($priceChangeM5 > $maxM5Pump) {
                $this->logFalse(sprintf("❌ Too overheated already: %.2f%% > %.2f%%", $priceChangeM5, $maxM5Pump));
                return false;
            }

            // --- Don’t fight strong downtrends ---
            if ($priceChangeH1 < $maxDownTrend || $priceChangeH6 < $maxDownTrend) {
                $this->logFalse(sprintf("❌ Strong downtrend detected (H1 %.2f%%, H6 %.2f%%)", $priceChangeH1, $priceChangeH6));
                return false;
            }

            // ✅ Passed → likely small pump incoming
            $this->buyReason = sprintf(
                "✅ JLP scalp setup | M5: %.2f%% | H1: %.2f%% | H6: %.2f%%",
                $priceChangeM5, $priceChangeH1, $priceChangeH6
            );
            Log::info($this->buyReason);

            return true;

        } catch (Throwable $e) {
            Log::warning("⚠️ canTradeWithJlpCheck exception for {$this->tokenAddress}: {$e->getMessage()}");
            return false;
        }
    }


    protected function checkMarketMetrics(): bool
    {
        // Fetch token data
        $this->tokenData = $this->tokenDataHelper->getTokenData($this->tokenAddress);
        if ($this->tokenData === null) return false;

        $marketCap      = $this->tokenData['marketCap'] ?? 0;
        $liquidity      = $this->tokenData['liquidity']['usd'] ?? 0;
        $volumeH1       = $this->tokenData['volume']['h1'] ?? 0;
        $priceChangeM5  = $this->tokenData['priceChange']['m5'] ?? 0;
        $priceChangeH1  = $this->tokenData['priceChange']['h1'] ?? 0;
        $priceChangeH6  = $this->tokenData['priceChange']['h6'] ?? 0;
        $priceChangeH24 = $this->tokenData['priceChange']['h24'] ?? 0;

        // --- Thresholds for 4% Scalps ---
        $minLiquidity   = 4000000;   // avoid tiny pools, but still volatile enough
        $maxLiquidity   = 20000000;  // cap it, too much liquidity = too slow
        $minMarketCap   = 1000000;   // avoid micro-rugs
        $maxMarketCap   = 50000000;  // mid-caps move fast enough for +4% pops
        $minVolumeH1    = 20000;     // ensures enough hourly activity for entry/exit
        $minVolLiqRatio = 0.5;       // volume must be healthy vs liquidity
        $minM5Gain      = 1;         // show some momentum
        $maxM5Gain      = 4;         // don’t chase pumps already past your target
        $minH1Gain      = -1;        // tolerate a tiny dip
        $maxH1Gain      = 10;        // filter out already mooning charts
        $minH6Gain      = 2;         // prefer overall upward trend
        $rugThreshold   = -20;       // hard stop if it nukes


        $allDrops = [$priceChangeM5, $priceChangeH1, $priceChangeH6, $priceChangeH24];

        // --- Rug filter ---
        foreach ($allDrops as $drop) {
            if (!is_numeric($drop) || $drop <= $rugThreshold) return false;
        }

        // --- Liquidity check ---
        if (
            !is_numeric($liquidity) ||
            $liquidity < $minLiquidity ||
            $liquidity > $maxLiquidity   // NEW: too much liquidity = too slow
        ) return false;

        // --- Market cap check ---
        if (!is_numeric($marketCap) || $marketCap < $minMarketCap || $marketCap > $maxMarketCap) return false;

        // --- Volume check ---
        $volLiqRatio = ($liquidity > 0) ? ($volumeH1 / $liquidity) : 0;
        $dynamicThreshold = $this->getDynamicVolLiqThreshold($liquidity);

        if ($volLiqRatio < $dynamicThreshold) {
            $this->logFalse(sprintf("❌ Vol/Liq ratio: %.2f < %.2f (dynamic)", $volLiqRatio, $dynamicThreshold));
            return false;
        }

        // --- Price trend checks ---
        if (!is_numeric($priceChangeH1) || $priceChangeH1 < $minH1Gain || $priceChangeH1 > $maxH1Gain) return false;
        if (!is_numeric($priceChangeM5) || $priceChangeM5 < $minM5Gain || $priceChangeM5 > $maxM5Gain) return false;
        if ($priceChangeM5 > 0 && $priceChangeH1 < 0) return false;
        if (!is_numeric($priceChangeH6) || $priceChangeH6 < $minH6Gain) return false;
        if ($priceChangeM5 > 2 && $volumeH1 < ($liquidity * 0.8)) return false;

        // --- Top avoidance filter ---
        $positiveThresholds = [
            'M5'  => 5,
            'H1'  => 10,
            'H6'  => 15,
            'H24' => 25,
        ];

        $allUp = 0;
        if ($priceChangeM5 > $positiveThresholds['M5']) $allUp++;
        if ($priceChangeH1 > $positiveThresholds['H1']) $allUp++;
        if ($priceChangeH6 > $positiveThresholds['H6']) $allUp++;
        if ($priceChangeH24 > $positiveThresholds['H24']) $allUp++;

        // If 3 or more timeframes are strongly positive → likely a top, skip buy
        if ($allUp >= 3) return false;

        // ✅ Passed all checks → store reason
        $this->buyReason = sprintf(
            "Passed metrics: MarketCap %.0f, Liquidity %.0f, VolumeH1 %.0f, M5 %.2f%%, H1 %.2f%%, H6 %.2f%%",
            human_readable_number($marketCap), human_readable_number($liquidity), $volumeH1, $priceChangeM5, $priceChangeH1, $priceChangeH6
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

    protected function getDynamicVolLiqThreshold(float $liquidity): float
    {
        if ($liquidity < 1_000_000) {
            return 1.0;   // small pools → need very high turnover
        } elseif ($liquidity < 5_000_000) {
            return 0.6;   // mid-small pools
        } elseif ($liquidity < 20_000_000) {
            return 0.4;   // mid-large pools
        } else {
            return 0.2;   // very large pools → allow lower turnover
        }
    }

}
