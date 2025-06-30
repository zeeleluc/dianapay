<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'crypto',
        'fiat',
        'blockchain',
        'rate',
        'recorded_at',
    ];

    protected $casts = [
        'rate' => 'decimal:8',
        'recorded_at' => 'datetime',
    ];

    public function scopeByCurrencyPair($query, string $fiat, string $crypto, string $blockchain)
    {
        return $query->where('fiat', $fiat)->where('crypto', $crypto)->where('blockchain', $blockchain);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('recorded_at', 'desc')->first();
    }
}
