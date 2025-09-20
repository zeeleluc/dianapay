<?php

namespace App\Http\Controllers;

use App\Models\SolanaCall;
use App\Helpers\SolanaTokenData;

class SniperController extends Controller
{
    protected SolanaTokenData $tokenDataHelper;

    public function __construct(SolanaTokenData $tokenDataHelper)
    {
        $this->tokenDataHelper = $tokenDataHelper;
    }

    public function index()
    {
        // Load Solana calls with their orders
        $solanaCalls = SolanaCall::with('orders')
            ->orderBy('created_at', 'desc')
            ->get();

        // Filter open positions
        $openCalls = $solanaCalls->filter(function ($call) {
            return $call->orders->where('type', 'buy')->isNotEmpty() &&
                $call->orders->where('type', 'sell')->isEmpty();
        });

        // Calculate latest unrealized profits for each open call
        foreach ($openCalls as $call) {
            $buyOrder = $call->orders->where('type', 'buy')->first();

            if ($buyOrder && $buyOrder->price_usd) {
                $latestData = $this->tokenDataHelper->getTokenData($call->token_address);
                $currentPrice = $latestData['price'] ?? null;
                $currentMarketCap = $latestData['marketCap'] ?? null;

                if ($currentPrice) {
                    $call->unrealized_profit_sol = (($currentPrice - $buyOrder->price_usd) / $buyOrder->price_usd) * 100;
                    $call->current_market_cap = $currentMarketCap;
                } else {
                    $call->unrealized_profit_sol = null;
                    $call->current_market_cap = null;
                }
            } else {
                $call->unrealized_profit_sol = null;
                $call->current_market_cap = null;
            }
        }

        return view('sniper.index', compact('solanaCalls'));
    }
}
