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
    ];

    public function solanaCall()
    {
        return $this->belongsTo(SolanaCall::class);
    }
}
