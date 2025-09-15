<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SolanaTokenData
{
    protected string $quickNodeEndpoint;
    protected string $addonPath = '/addon/912/networks/solana';
    protected int $maxRetries = 3; // Increased from 2 for more resilience
    protected int $retryDelaySeconds = 5; // Increased from 2 to avoid rate limits

    public function __construct()
    {
        $this->quickNodeEndpoint = config('services.quicknode.endpoint');
        if (empty($this->quickNodeEndpoint)) {
            Log::error('QuickNode endpoint not configured in services.quicknode.endpoint');
            throw new \Exception('QuickNode endpoint configuration missing');
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
        // Validate token address format (base58, 44 characters)
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{44}$/', $tokenAddress)) {
            Log::warning("Invalid token address: {$tokenAddress}");
            return null;
        }

        // Skip new tokens (less than 10 minutes old) to allow indexing
        if ($createdAt && now()->diffInMinutes($createdAt) < 10) {
            Log::info("Skipping token {$tokenAddress}: too new, created at {$createdAt->toDateTimeString()}");
            return null;
        }

        $cacheKey = "token_data_{$tokenAddress}";
        return Cache::remember($cacheKey, 300, function () use ($tokenAddress) {
            $attempt = 0;
            $tokenUrl = "{$this->quickNodeEndpoint}{$this->addonPath}/tokens/{$tokenAddress}";

            while ($attempt <= $this->maxRetries) {
                try {
                    // Track request count for monitoring
                    Cache::increment("quicknode_requests_" . now()->format('YmdHi'));

                    // Fetch token overview with increased timeout
                    $response = Http::timeout(10)->get($tokenUrl);

                    if ($response->status() === 429) {
                        Log::warning("QuickNode /tokens API rate limit hit for {$tokenAddress} (attempt " . ($attempt + 1) . ")", [
                            'url' => $tokenUrl,
                            'status' => $response->status(),
                        ]);
                        $attempt++;
                        if ($attempt <= $this->maxRetries) {
                            sleep($this->retryDelaySeconds * pow(2, $attempt)); // Exponential backoff
                            continue;
                        }
                        return null;
                    }

                    if ($response->status() === 404) {
                        Log::info("Token {$tokenAddress} not found or not indexed by QuickNode", [
                            'url' => $tokenUrl,
                            'status' => $response->status(),
                        ]);
                        return null;
                    }

                    if (!$response->successful()) {
                        Log::warning("QuickNode /tokens API failed for {$tokenAddress} (attempt " . ($attempt + 1) . ")", [
                            'url' => $tokenUrl,
                            'status' => $response->status(),
                            'response' => $response->body() ?: 'Empty response',
                            'error' => $response->json('error') ?? 'Unknown error',
                        ]);
                        $attempt++;
                        if ($attempt <= $this->maxRetries) {
                            sleep($this->retryDelaySeconds * pow(2, $attempt)); // Exponential backoff
                            continue;
                        }
                        return null;
                    }

                    $data = $response->json();
                    if (null === $data || !isset($data['summary'])) {
                        Log::warning("QuickNode /tokens API returned invalid response for {$tokenAddress}", [
                            'url' => $tokenUrl,
                            'response' => $response->body() ?: 'Empty response',
                            'data' => $data ?? 'No data',
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
                        'response' => isset($response) ? ($response->body() ?: 'Empty response') : 'No response',
                    ]);
                    $attempt++;
                    if ($attempt <= $this->maxRetries) {
                        sleep($this->retryDelaySeconds * pow(2, $attempt)); // Exponential backoff
                        continue;
                    }
                    return null;
                }
            }
            return null;
        });
    }
}
