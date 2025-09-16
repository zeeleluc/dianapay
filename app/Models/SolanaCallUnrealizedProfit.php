<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolanaCallUnrealizedProfit extends Model
{
    use HasFactory;

    protected $fillable = [
        'solana_call_id',
        'unrealized_profit',
        'buy_market_cap',
        'current_market_cap',
    ];

    public function solanaCall()
    {
        return $this->belongsTo(SolanaCall::class);
    }
}
