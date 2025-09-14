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
        $solanaCalls = SolanaCall::with('orders')
            ->orderBy('created_at', 'desc')
            ->get();

        $openCalls = $solanaCalls->filter(function ($call) {
            return $call->orders->where('type', 'buy')->isNotEmpty() &&
                $call->orders->where('type', 'sell')->isEmpty();
        });

        foreach ($openCalls as $call) {
            $cacheKey = "market_cap_{$call->token_address}";
            $call->current_market_cap = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($call) {
                try {
                    $response = Http::timeout(5)->get("https://api.dexscreener.com/latest/dex/tokens/{$call->token_address}");
                    if (!$response->successful()) {
                        Log::warning("DexScreener API failed for {$call->token_address}", [
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);
                        return 0;
                    }
                    $data = $response->json();
                    if (empty($data['pairs'][0])) {
                        Log::warning("No pairs found for {$call->token_address}", ['response' => $data]);
                        return 0;
                    }
                    $marketCap = $data['pairs'][0]['marketCap'] ?? 0;
                    if ($marketCap <= 0) {
                        Log::warning("Invalid market cap for {$call->token_address}", ['marketCap' => $marketCap]);
                    }
                    return $marketCap;
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch market cap for {$call->token_address}: {$e->getMessage()}", [
                        'exception' => $e->getTraceAsString(),
                    ]);
                    return 0;
                }
            });
            // Log for debugging
            Log::debug("Market cap for {$call->token_address}", [
                'current_market_cap' => $call->current_market_cap,
                'buy_market_cap' => $call->market_cap,
            ]);
        }

        return view('sniper.index', compact('solanaCalls'));
    }
}
