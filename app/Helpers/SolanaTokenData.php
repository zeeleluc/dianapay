<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SolanaTokenData
{
    protected string $quickNodeEndpoint;
    protected string $addonPath = '/addon/912/networks/solana';
    protected int $maxRetries = 2;
    protected int $retryDelaySeconds = 2;

    public function __construct()
    {
        $this->quickNodeEndpoint = config('services.quicknode.endpoint');
        if (empty($this->quickNodeEndpoint)) {
            Log::error("QuickNode endpoint not configured in services.quicknode.endpoint");
            throw new \Exception("QuickNode endpoint configuration missing");
        }
    }

    /**
     * Fetch token data (price, liquidity, price changes) for a Solana contract address using QuickNode add-on.
     *
     * @param string $tokenAddress Solana token contract address
     * @param \DateTime|null $createdAt Optional token creation time to check for new tokens
     * @return array|null Returns array with price, liquidity, priceChange (m5, h1, h6, h24), volume, marketCap or null on failure
     */
    public function getTokenData(string $tokenAddress, ?\DateTime $createdAt = null): ?array
    {
        // Skip new tokens (less than 10 minutes old) to allow indexing
        if ($createdAt && now()->diffInMinutes($createdAt) < 10) {
            Log::info("Skipping token {$tokenAddress}: too new, created at {$createdAt->toDateTimeString()}");
            return null;
        }

        $cacheKey = "token_data_{$tokenAddress}";
        return Cache::remember($cacheKey, 30, function () use ($tokenAddress) {
            $attempt = 0;
            while ($attempt <= $this->maxRetries) {
                try {
                    // Fetch token overview
                    $tokenUrl = "{$this->quickNodeEndpoint}{$this->addonPath}/tokens/{$tokenAddress}";
                    $response = Http::timeout(5)->get($tokenUrl);

                    if (!$response->successful()) {
                        Log::warning("QuickNode /tokens API failed for {$tokenAddress} (attempt " . ($attempt + 1) . ")", [
                            'url' => $tokenUrl,
                            'status' => $response->status(),
                            'response' => $response->body(),
                            'error' => $response->json('error') ?? 'Unknown error',
                        ]);
                        $attempt++;
                        if ($attempt <= $this->maxRetries) {
                            sleep($this->retryDelaySeconds);
                            continue;
                        }
                        return null;
                    }

                    $data = $response->json();
                    if (null === $data || !isset($data['summary'])) {
                        Log::warning("QuickNode /tokens API invalid response for {$tokenAddress}", [
                            'url' => $tokenUrl,
                            'response' => $response->body(),
                        ]);
                        return null;
                    }

                    // Extract from token overview
                    $currentPrice = $data['summary']['price_usd'] ?? 0;
                    $currentLiquidity = $data['summary']['liquidity_usd'] ?? 0;
                    $priceChangeM5 = $data['summary']['5m']['last_price_usd_change'] ?? 0;
                    $priceChangeH1 = $data['summary']['1h']['last_price_usd_change'] ?? 0;
                    $priceChangeH6 = $data['summary']['6h']['last_price_usd_change'] ?? 0;
                    $priceChangeH24 = $data['summary']['24h']['last_price_usd_change'] ?? 0;
                    $volumeH1 = $data['summary']['1h']['volume_usd'] ?? 0;
                    $marketCap = $data['summary']['fdv'] ?? 0;

                    // Log successful fetch
                    Log::info("Successfully fetched token data for {$tokenAddress}", [
                        'price' => $currentPrice,
                        'liquidity' => $currentLiquidity,
                        'priceChange' => ['m5' => $priceChangeM5, 'h1' => $priceChangeH1, 'h6' => $priceChangeH6, 'h24' => $priceChangeH24],
                        'volume_h1' => $volumeH1,
                        'marketCap' => $marketCap,
                    ]);

                    return [
                        'price' => $currentPrice,
                        'liquidity' => ['usd' => $currentLiquidity],
                        'priceChange' => [
                            'm5' => $priceChangeM5,
                            'h1' => $priceChangeH1,
                            'h6' => $priceChangeH6,
                            'h24' => $priceChangeH24,
                        ],
                        'volume' => ['h1' => $volumeH1],
                        'marketCap' => $marketCap,
                    ];
                } catch (\Throwable $e) {
                    Log::error("QuickNode API request exception for {$tokenAddress} (attempt " . ($attempt + 1) . ")", [
                        'url' => $tokenUrl,
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'response' => $response ? $response->body() : 'No response',
                    ]);
                    $attempt++;
                    if ($attempt <= $this->maxRetries) {
                        sleep($this->retryDelaySeconds);
                        continue;
                    }
                    return null;
                }
            }
            return null;
        });
    }
}
