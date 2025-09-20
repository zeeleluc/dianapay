<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SolanaTokenData
{
    protected int $maxRetries = 3;
    protected int $retryDelaySeconds = 5;

    /**
     * Fetch token data (price, liquidity, price changes, volume, market cap) for a Solana token using Dexscreener.
     *
     * @param string $tokenAddress Solana token contract address
     * @param \DateTime|null $createdAt Optional token creation time to skip very new tokens
     * @return array|null
     */
    public function getTokenData(string $tokenAddress, ?\DateTime $createdAt = null): ?array
    {
        // Validate Solana token address (base58)
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{44}$/', $tokenAddress)) {
            Log::warning("Invalid token address: {$tokenAddress}");
            return null;
        }

        // Skip very new tokens
        if ($createdAt && now()->diffInMinutes($createdAt) < 10) {
            Log::info("Skipping token {$tokenAddress}: too new, created at {$createdAt->toDateTimeString()}");
            return null;
        }

        $cacheKey = "token_data_{$tokenAddress}";

        return Cache::remember($cacheKey, 300, function () use ($tokenAddress) {
            $attempt = 0;
            $dexscreenerUrl = "https://api.dexscreener.com/latest/dex/tokens/{$tokenAddress}";

            while ($attempt <= $this->maxRetries) {
                try {
                    $response = Http::timeout(10)->get($dexscreenerUrl);

                    if ($response->status() === 404) {
                        Log::info("Token {$tokenAddress} not found on Dexscreener");
                        return null;
                    }

                    if (!$response->successful()) {
                        Log::warning("Dexscreener API failed for {$tokenAddress} (attempt " . ($attempt + 1) . ")", [
                            'status' => $response->status(),
                            'response' => $response->body(),
                        ]);
                        $attempt++;
                        if ($attempt <= $this->maxRetries) {
                            sleep($this->retryDelaySeconds * pow(2, $attempt));
                            continue;
                        }
                        return null;
                    }

                    $data = $response->json();
                    if (!isset($data['pairs']) || empty($data['pairs'])) {
                        Log::warning("No pairs data found for {$tokenAddress} on Dexscreener");
                        return null;
                    }

                    // Use the first pair
                    $pair = $data['pairs'][0];

                    // Extract data safely
                    $currentPrice = $pair['priceUsd'] ?? 0;
                    $currentLiquidity = $pair['liquidity']['usd'] ?? 0;

                    $volume = [
                        'm5'  => $pair['volume']['m5'] ?? 0,
                        'h1'  => $pair['volume']['h1'] ?? 0,
                        'h6'  => $pair['volume']['h6'] ?? 0,
                        'h24' => $pair['volume']['h24'] ?? 0,
                    ];

                    $priceChange = [
                        'm5'  => $pair['priceChange']['m5'] ?? 0,
                        'h1'  => $pair['priceChange']['h1'] ?? 0,
                        'h6'  => $pair['priceChange']['h6'] ?? 0,
                        'h24' => $pair['priceChange']['h24'] ?? 0,
                    ];

                    $marketCap = $currentPrice * ($pair['circulatingSupply'] ?? 0);

                    Log::info("Fetched Dexscreener token data for {$tokenAddress}", [
                        'price' => $currentPrice,
                        'liquidity' => $currentLiquidity,
                        'volume' => $volume,
                        'priceChange' => $priceChange,
                        'marketCap' => $marketCap,
                    ]);

                    return [
                        'price'       => $currentPrice,
                        'liquidity'   => ['usd' => $currentLiquidity],
                        'priceChange' => $priceChange,
                        'volume'      => $volume,
                        'marketCap'   => $marketCap,
                    ];
                } catch (\Throwable $e) {
                    Log::error("Dexscreener API request exception for {$tokenAddress} (attempt " . ($attempt + 1) . ")", [
                        'message' => $e->getMessage(),
                        'code'    => $e->getCode(),
                    ]);
                    $attempt++;
                    if ($attempt <= $this->maxRetries) {
                        sleep($this->retryDelaySeconds * pow(2, $attempt));
                        continue;
                    }
                    return null;
                }
            }

            return null;
        });
    }
}
