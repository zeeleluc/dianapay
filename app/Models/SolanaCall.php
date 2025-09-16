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
        'dev_sold',
        'dex_paid_status',
        'strategy',
        'previous_unrealized_profits', // added
    ];

    protected $casts = [
        'market_cap' => 'decimal:2',
        'volume_24h' => 'decimal:2',
        'liquidity_pool' => 'decimal:2',
        'all_time_high' => 'decimal:2',
        'top_10_holders_percent' => 'decimal:2',
        'dev_sold' => 'boolean',
        'dex_paid_status' => 'boolean',
        'previous_unrealized_profits' => 'decimal:8', // added
    ];

    public static function createFromParsed(array $data): self
    {
        return self::create($data);  // Inserts nulls for missing fields
    }

    public function orders()
    {
        return $this->hasMany(SolanaCallOrder::class);
    }

    /**
     * Calculate profit based on SOL.
     *
     * Profit = total SOL sold - total SOL bought
     *
     * @return float
     */
    public function profit(): string
    {
        $profit = 0;

        foreach ($this->orders as $order) {
            if (strtolower($order->type) === 'buy') {
                $profit -= $order->amount_sol; // spent SOL
            } elseif (strtolower($order->type) === 'sell') {
                $profit += $order->amount_sol; // gained SOL
            }
        }

        // Format with 8 decimals, no scientific notation
        return number_format($profit, 8, '.', '');
    }

    public function profitPercentage(): string
    {
        $totalBought = 0.0;
        $totalSold   = 0.0;

        foreach ($this->orders as $order) {
            $type = strtolower($order->type);
            if ($type === 'buy') {
                $totalBought += $order->amount_sol;
            } elseif ($type === 'sell') {
                $totalSold += $order->amount_sol;
            }
        }

        if ($totalBought == 0) {
            return '0.00'; // avoid division by zero
        }

        $percentage = (($totalSold - $totalBought) / $totalBought) * 100;

        // Format to 2 decimals, no scientific notation
        return number_format($percentage, 2, '.', '');
    }

    /**
     * Get total profit of all SolanaCalls in SOL,
     * only including calls with both buy and sell orders.
     *
     * @return string
     */
    public static function totalProfitSol(): string
    {
        $totalProfit = 0.0;

        $calls = self::with('orders')->get();

        foreach ($calls as $call) {
            $hasBuy = $call->orders->where('type', 'buy')->isNotEmpty();
            $hasSell = $call->orders->where('type', 'sell')->isNotEmpty();

            if (!($hasBuy && $hasSell)) {
                continue; // skip calls without both buy and sell
            }

            foreach ($call->orders as $order) {
                $type = strtolower($order->type);
                if ($type === 'buy') {
                    $totalProfit -= $order->amount_sol ?? 0.0;
                } elseif ($type === 'sell') {
                    $totalProfit += $order->amount_sol ?? 0.0;
                }
            }
        }

        return number_format($totalProfit, 8, '.', '');
    }

    /**
     * Get total profit percentage of all SolanaCalls,
     * only including calls with both buy and sell orders.
     *
     * @return string
     */
    public static function totalProfitPercentage(): string
    {
        $totalBought = 0.0;
        $totalSold   = 0.0;

        $calls = self::with('orders')->get();

        foreach ($calls as $call) {
            $hasBuy = $call->orders->where('type', 'buy')->isNotEmpty();
            $hasSell = $call->orders->where('type', 'sell')->isNotEmpty();

            if (!($hasBuy && $hasSell)) {
                continue; // skip calls without both buy and sell
            }

            foreach ($call->orders as $order) {
                $type = strtolower($order->type);
                if ($type === 'buy') {
                    $totalBought += $order->amount_sol ?? 0.0;
                } elseif ($type === 'sell') {
                    $totalSold += $order->amount_sol ?? 0.0;
                }
            }
        }

        if ($totalBought == 0.0) {
            return '0.00';
        }

        $percentage = (($totalSold - $totalBought) / $totalBought) * 100;

        return number_format($percentage, 2, '.', '');
    }

}
