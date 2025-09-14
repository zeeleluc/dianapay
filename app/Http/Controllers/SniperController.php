<?php

namespace App\Http\Controllers;

use App\Models\SolanaCall;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SniperController extends Controller
{
    public function index()
    {
        // Get all SolanaCall records, newest first, eager load related orders
        $solanaCalls = SolanaCall::with('orders')
            ->orderBy('created_at', 'desc')
            ->get();

        // Fetch current market cap for open calls
        $openCalls = $solanaCalls->filter(function ($call) {
            return $call->orders->where('type', 'buy')->isNotEmpty() &&
                $call->orders->where('type', 'sell')->isEmpty();
        });

        foreach ($openCalls as $call) {
            $cacheKey = "market_cap_{$call->token_address}";
            $call->current_market_cap = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($call) {
                try {
                    $response = Http::timeout(5)->get("https://api.dexscreener.com/latest/dex/tokens/{$call->token_address}");
                    return $response->successful() && !empty($response->json('pairs.0')) ? $response->json('pairs.0.marketCap') : 0;
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch market cap for {$call->token_address}: {$e->getMessage()}");
                    return 0;
                }
            });
        }

        return view('sniper.index', compact('solanaCalls'));
    }
}
