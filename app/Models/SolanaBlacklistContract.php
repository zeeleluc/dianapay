<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolanaBlacklistContract extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'solana_blacklist_contracts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['contract'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Check if a contract address is blacklisted.
     *
     * @param string $contractAddress
     * @return bool
     */
    public static function isBlacklisted(string $contractAddress): bool
    {
        return self::where('contract', $contractAddress)->exists();
    }
}
