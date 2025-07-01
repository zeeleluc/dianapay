<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class AnonymousPaymentRequest extends Model
{
    use HasFactory;

    protected $table = 'anonymous_payment_requests';

    protected $fillable = [
        'identifier',
        'fiat',
        'amount_minor',
        'to_wallet',
        'to_wallet_evm',
        'to_wallet_solana',
        'to_wallet_bitcoin',
        'to_wallet_xrp',
        'to_wallet_cardano',
        'to_wallet_algorand',
        'to_wallet_stellar',
        'to_wallet_tezos',
        'description',
        'accepted_crypto',
        'crypto',
        'rate',
        'transaction_tx',
        'status',
        'paid_at',
        'has_qr_image',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'rate' => 'decimal:10',
        'paid_at' => 'datetime',
        // Wallet fields als string casten (optioneel, want standaard string)
        'to_wallet_evm' => 'string',
        'to_wallet_solana' => 'string',
        'to_wallet_bitcoin' => 'string',
        'to_wallet_xrp' => 'string',
        'to_wallet_cardano' => 'string',
        'to_wallet_algorand' => 'string',
        'to_wallet_stellar' => 'string',
        'to_wallet_tezos' => 'string',
        'has_qr_image' => 'boolean',
    ];

    protected $dates = [
        'paid_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Boot function to auto-generate UUID for `identifier`.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->identifier)) {
                $model->identifier = (string) Str::uuid();
            }
        });
    }

    public function getAcceptedCryptoArrayAttribute(): array
    {
        return json_decode($this->accepted_crypto ?? '[]', true);
    }
}
