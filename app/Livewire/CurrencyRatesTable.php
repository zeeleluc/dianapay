<?php

namespace App\Livewire;

use App\Models\CurrencyRate;
use Livewire\Component;

class CurrencyRatesTable extends Component
{
    public function render()
    {
        return view('livewire.currency-rates-table', [
            'ratesByBlockchain' => $this->getRatesByBlockchain(),
        ]);
    }

    private function getRatesByBlockchain(): array
    {
        $configuredBlockchains = config('cryptocurrencies', []);
        $fiats = config('fiats', []);
        $result = [];

        foreach ($configuredBlockchains as $blockchain => $tokens) {
            if (empty($tokens)) {
                continue;
            }

            $cryptoSymbols = array_keys($tokens);
            sort($cryptoSymbols);
            sort($fiats);

            $data = [];

            foreach ($cryptoSymbols as $crypto) {
                $data[$crypto] = [];

                foreach ($fiats as $fiat) {
                    $rate = CurrencyRate::byCurrencyPair($fiat, $crypto, $blockchain)
                        ->latest()
                        ->value('rate');

                    $data[$crypto][$fiat] = $rate ?? null;
                }
            }

            $result[$blockchain] = [
                'fiats' => $fiats,
                'cryptos' => $cryptoSymbols,
                'data' => $data,
            ];
        }

        return $result;
    }
}
