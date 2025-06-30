<?php

namespace App\Console\Commands;

use App\Services\CurrencyRateService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchCryptoRates extends Command
{
    protected $signature = 'crypto:fetch-rates';
    protected $description = 'Fetch cryptocurrency exchange rates from CoinGecko and store them in the currency_rates table';

    public function handle()
    {
        // Load configurations
        $blockchains = config('cryptocurrencies.coingecko', []);

        if (empty($blockchains)) {
            $this->error('No blockchains configured in cryptocurrencies.php');
            return 1;
        }

        try {
            foreach ($blockchains as $blockchain => $config) {
                $cryptoIds = $config['currencies']['crypto_ids'] ?? [];
                $fiatCurrencies = $config['currencies']['fiat'] ?? [];
                $cryptoMap = $config['crypto_map'] ?? [];

                if (empty($cryptoIds) || empty($fiatCurrencies)) {
                    $this->error("No cryptocurrencies or fiat currencies configured for blockchain {$blockchain}");
                    continue;
                }

                // Fetch rates from CoinGecko (excluding XCG, as it may not be supported)
                $fetchableFiats = array_diff($fiatCurrencies, ['xcg']);
                $response = Http::get('https://api.coingecko.com/api/v3/simple/price', [
                    'ids' => implode(',', $cryptoIds),
                    'vs_currencies' => implode(',', $fetchableFiats),
                ]);

                if (!$response->successful()) {
                    $this->error("Failed to fetch rates from CoinGecko for blockchain {$blockchain}: " . $response->status());
                    continue;
                }

                $data = $response->json();
                $recordedAt = Carbon::now()->startOfMinute();

                foreach ($cryptoIds as $cryptoId) {
                    if (!isset($data[$cryptoId])) {
                        $this->warn("No data returned for {$cryptoId} on {$blockchain}");
                        continue;
                    }

                    $crypto = $cryptoMap[$cryptoId] ?? strtoupper($cryptoId);

                    foreach ($fiatCurrencies as $fiat) {
                        $rate = null;
                        if ($fiat === 'xcg') {
                            // Derive XCG rate from USD rate (1 USD = 1.79 XCG)
                            if (isset($data[$cryptoId]['usd'])) {
                                $usdRate = $data[$cryptoId]['usd'];
                                $rate = $usdRate / 1.79; // e.g., 1 USDT = 1 USD → 1 USDT = 0.558659 XCG
                            }
                        } else {
                            $rate = $data[$cryptoId][$fiat] ?? null;
                        }

                        if ($rate === null) {
                            $this->warn("No {$fiat} price found for {$crypto} on {$blockchain}");
                            continue;
                        }

                        // Store the rate using CurrencyRateService, which skips duplicates
                        try {
                            $currencyRate = CurrencyRateService::storeRate(
                                fiat: strtoupper($fiat),
                                crypto: $crypto,
                                blockchain: $blockchain,
                                rate: $rate,
                                date: $recordedAt
                            );
                            $this->info("Processed rate: {$currencyRate->fiat}/{$currencyRate->crypto} ({$currencyRate->blockchain}) = {$currencyRate->rate} at {$currencyRate->recorded_at}");
                        } catch (\InvalidArgumentException $e) {
                            $this->error("Failed to store rate for {$fiat}/{$crypto} on {$blockchain}: {$e->getMessage()}");
                        }
                    }
                }
            }

            $this->info('Successfully processed all rates.');
            return 0;

        } catch (\Exception $e) {
            $this->error('Error fetching rates from CoinGecko: ' . $e->getMessage());
            return 1;
        }
    }
}
