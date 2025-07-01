<?php

namespace App\Console\Commands;

use App\Models\CurrencyRate;
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
        $blockchains = config('cryptocurrencies', []);
        $fiatCurrencies = array_keys(config('fiats', []));

        if (empty($blockchains)) {
            $this->error('No cryptocurrencies configured.');
            return 1;
        }

        foreach ($blockchains as $blockchain => $tokens) {
            if (empty($tokens)) {
                $this->warn("No tokens configured for blockchain: {$blockchain}");
                continue;
            }

            // Build crypto_id list and symbol map
            $cryptoIds = [];
            $cryptoMap = [];
            foreach ($tokens as $symbol => $info) {
                if (!isset($info['coingecko_id'])) continue;

                $cryptoIds[] = $info['coingecko_id'];
                $cryptoMap[$info['coingecko_id']] = $symbol;
            }

            $fetchableFiats = array_diff($fiatCurrencies, ['xcg']);
            $response = Http::get('https://api.coingecko.com/api/v3/simple/price', [
                'ids' => implode(',', $cryptoIds),
                'vs_currencies' => implode(',', $fetchableFiats),
            ]);

            if (!$response->successful()) {
                $this->error("Failed to fetch rates from CoinGecko for {$blockchain}: " . $response->status());
                continue;
            }

            $data = $response->json();
            $recordedAt = Carbon::now()->startOfMinute();

            foreach ($cryptoIds as $cryptoId) {
                if (!isset($data[$cryptoId])) {
                    $this->warn("No data for {$cryptoId} on {$blockchain}");
                    continue;
                }

                $cryptoSymbol = $cryptoMap[$cryptoId];

                foreach ($fiatCurrencies as $fiat) {
                    $rate = null;

                    if ($fiat === 'xcg') {
                        $usdRate = $data[$cryptoId]['usd'] ?? null;
                        if ($usdRate) {
                            $rate = $usdRate / 1.79;
                        }
                    } else {
                        $rate = $data[$cryptoId][$fiat] ?? null;
                    }

                    if ($rate === null) {
                        $this->warn("Missing rate for {$cryptoSymbol}/{$fiat} on {$blockchain}");
                        continue;
                    }

                    try {
                        $currencyRate = CurrencyRateService::storeRate(
                            fiat: strtoupper($fiat),
                            crypto: $cryptoSymbol,
                            blockchain: $blockchain,
                            rate: $rate,
                            date: $recordedAt
                        );

                        $this->info("Saved: {$fiat}/{$cryptoSymbol} ({$blockchain}) = {$rate} @ {$recordedAt}");
                    } catch (\InvalidArgumentException $e) {
                        $this->error("Store failed for {$fiat}/{$cryptoSymbol} on {$blockchain}: {$e->getMessage()}");
                    }
                }
            }
        }

        $deleted = CurrencyRate::where('recorded_at', '<', Carbon::now()->subDay())->delete();
        $this->info("Cleaned up {$deleted} old currency rate(s).");

        return 0;
    }
}
