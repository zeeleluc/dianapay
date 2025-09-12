<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolanaCall extends Model
{
    use HasFactory;

    protected $fillable = [
        'token_name',
        'token_address',
        'age_minutes',
        'market_cap',
        'volume_24h',
        'liquidity_pool',
        'all_time_high',
        'top_10_holders_percent',
        'dev_sold',  // Boolean: true if 🟢
        'dex_paid_status',  // Boolean: true if 🟢
    ];

    // Casts: Decimals and booleans handle nulls gracefully
    protected $casts = [
        'market_cap' => 'decimal:2',
        'volume_24h' => 'decimal:2',
        'liquidity_pool' => 'decimal:2',
        'all_time_high' => 'decimal:2',
        'top_10_holders_percent' => 'decimal:2',
        'dev_sold' => 'boolean',
        'dex_paid_status' => 'boolean',
    ];

    // Helper method to save parsed data
    public static function createFromParsed(array $data): self
    {
        return self::create($data);  // Inserts nulls for missing fields
    }

    public function orders()
    {
        return $this->hasMany(SolanaCallOrder::class);
    }
}
