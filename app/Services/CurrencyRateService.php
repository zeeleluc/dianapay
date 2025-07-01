<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class CurrencyRateService
{
    public static function getRate(string $fiat, string $crypto, string $blockchain): ?CurrencyRate
    {
        return CurrencyRate::byCurrencyPair($fiat, $crypto, $blockchain)
            ->orderBy('recorded_at', 'desc')
            ->first();
    }

    public static function storeRate(string $fiat, string $crypto, string $blockchain, float $rate, $date = null): CurrencyRate
    {
        $date = $date instanceof Carbon ? $date->startOfMinute() : Carbon::parse($date ?? now())->startOfMinute();
        $existingRate = CurrencyRate::byCurrencyPair($fiat, $crypto, $blockchain)
            ->where('recorded_at', $date)
            ->first();

        if ($existingRate) {
            return $existingRate;
        }

        if ($rate <= 0) {
            throw new \InvalidArgumentException("Rate must be positive for {$fiat}/{$crypto} on {$blockchain}.");
        }

        return CurrencyRate::create([
            'fiat' => $fiat,
            'crypto' => $crypto,
            'blockchain' => $blockchain,
            'rate' => $rate,
            'recorded_at' => $date,
        ]);
    }
}
