<?php

namespace App\Http\Controllers;

use App\Models\SolanaCall;
use App\Models\SolanaCallUnrealizedProfit;
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

        // Fetch latest unrealized profits for each open call
        foreach ($openCalls as $call) {
            $latestProfit = SolanaCallUnrealizedProfit::where('solana_call_id', $call->id)
                ->latest('created_at')
                ->first();

            if ($latestProfit) {
                $call->unrealized_profit_sol = $latestProfit->unrealized_profit;
                $call->current_market_cap = $latestProfit->current_market_cap;
            } else {
                $call->unrealized_profit_sol = null;
                $call->current_market_cap = null;
            }
        }

        return view('sniper.index', compact('solanaCalls'));
    }
}
