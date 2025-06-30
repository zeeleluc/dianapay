<?php

namespace App\Livewire;

use App\Models\CurrencyRate;
use Livewire\Component;

class CurrencyRatesTable extends Component
{
    public function render()
    {
        $ratesByBlockchain = $this->getRatesByBlockchain();
        return view('livewire.currency-rates-table', [
            'ratesByBlockchain' => $ratesByBlockchain,
        ]);
    }

    private function getRatesByBlockchain(): array
    {
        // Get distinct blockchains
        $blockchains = CurrencyRate::distinct()->pluck('blockchain')->toArray();
        $result = [];

        foreach ($blockchains as $blockchain) {
            // Get unique fiats and cryptos for this blockchain
            $fiats = CurrencyRate::where('blockchain', $blockchain)
                ->distinct()
                ->pluck('fiat')
                ->sort()
                ->values()
                ->toArray();
            $cryptos = CurrencyRate::where('blockchain', $blockchain)
                ->distinct()
                ->pluck('crypto')
                ->sort()
                ->values()
                ->toArray();

            // Build the matrix using the latest rate for each fiat/crypto pair
            $data = [];
            foreach ($cryptos as $crypto) {
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
                'cryptos' => $cryptos,
                'data' => $data,
            ];
        }

        return $result;
    }
}
