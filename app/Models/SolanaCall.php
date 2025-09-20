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
        'reason_buy',
        'reason_sell',
    ];

    protected $casts = [
        'market_cap' => 'decimal:2',
        'volume_24h' => 'decimal:2',
        'liquidity_pool' => 'decimal:2',
        'all_time_high' => 'decimal:2',
        'top_10_holders_percent' => 'decimal:2',
        'dev_sold' => 'boolean',
        'dex_paid_status' => 'boolean',
    ];

    // -----------------------------
    // Relationships
    // -----------------------------
    public function orders()
    {
        return $this->hasMany(SolanaCallOrder::class);
    }

    // -----------------------------
    // Profit calculations (SOL)
    // -----------------------------
    public function profit(): string
    {
        $profit = 0.0;

        foreach ($this->orders as $order) {
            $type = strtolower($order->type);
            if ($type === 'buy') {
                $profit -= $order->amount_sol;
            } elseif ($type === 'sell') {
                $profit += $order->amount_sol;
            }
        }

        return number_format($profit, 8, '.', '');
    }

    public function profitPercentage(): string
    {
        $totalBuy = $this->orders->where('type', 'buy')->sum('amount_sol');
        $totalSell = $this->orders->where('type', 'sell')->sum('amount_sol');

        if ($totalBuy <= 0) return '0.00';

        $profit = $totalSell - $totalBuy;
        $percentage = ($profit / $totalBuy) * 100;

        return number_format($percentage, 2, '.', '');
    }

    // -----------------------------
    // Profit calculations (USD)
    // -----------------------------
    public function profitUsd(): string
    {
        $buyOrder = $this->orders->where('type', 'buy')->first();
        $sellOrder = $this->orders->where('type', 'sell')->first();

        if (!$buyOrder || !$sellOrder || !$buyOrder->price_usd || !$sellOrder->price_usd) {
            return '0.00';
        }

        $profit = ($sellOrder->price_usd * $sellOrder->amount_foreign) - ($buyOrder->price_usd * $buyOrder->amount_foreign);
        return number_format($profit, 2, '.', '');
    }

    public function profitPercentageUsd(): string
    {
        $buyOrder = $this->orders->where('type', 'buy')->first();
        $sellOrder = $this->orders->where('type', 'sell')->first();

        if (!$buyOrder || !$sellOrder || !$buyOrder->price_usd || !$sellOrder->price_usd) {
            return '0.00';
        }

        $percentage = (($sellOrder->price_usd - $buyOrder->price_usd) / $buyOrder->price_usd) * 100;
        return number_format($percentage, 2, '.', '');
    }

    // -----------------------------
    // Static helper methods
    // -----------------------------
    /**
     * Total realized profit for all calls in SOL (only calls with both buy & sell)
     */
    public static function totalProfitSol(): string
    {
        $totalProfit = 0.0;
        $calls = self::with('orders')->get();

        foreach ($calls as $call) {
            if ($call->orders->where('type', 'buy')->isNotEmpty() && $call->orders->where('type', 'sell')->isNotEmpty()) {
                $totalProfit += (float) $call->profit();
            }
        }

        return number_format($totalProfit, 8, '.', '');
    }

    /**
     * Total realized profit percentage for all calls in SOL
     */
    public static function totalProfitPercentage(): string
    {
        $totalBuy = 0.0;
        $totalSell = 0.0;
        $calls = self::with('orders')->get();

        foreach ($calls as $call) {
            $buySum = $call->orders->where('type', 'buy')->sum('amount_sol');
            $sellSum = $call->orders->where('type', 'sell')->sum('amount_sol');

            if ($buySum <= 0 || $sellSum <= 0) continue;

            $totalBuy += $buySum;
            $totalSell += $sellSum;
        }

        if ($totalBuy <= 0) return '0.00';

        $percentage = (($totalSell - $totalBuy) / $totalBuy) * 100;
        return number_format($percentage, 2, '.', '');
    }

    // -----------------------------
    // Factory helper (optional)
    // -----------------------------
    public static function createFromParsed(array $data): self
    {
        return self::create($data);
    }
}
