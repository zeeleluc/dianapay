<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolanaCallOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'solana_call_id',
        'type',
        'amount_foreign',
        'amount_sol',
        'dex_used',
        'error',
        'tx_signature',

    ];

    protected $casts = [
        'amount_foreign' => 'decimal:8',
        'amount_sol' => 'decimal:9',
    ];

    public function solanaCall()
    {
        return $this->belongsTo(SolanaCall::class);
    }
}
