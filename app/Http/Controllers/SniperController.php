<?php

namespace App\Http\Controllers;

use App\Models\SolanaCall;
use App\Helpers\SolanaTokenData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SniperController extends Controller
{
    protected SolanaTokenData $tokenDataHelper;

    public function __construct(SolanaTokenData $tokenDataHelper)
    {
        $this->tokenDataHelper = $tokenDataHelper;
    }

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
            try {
                // Fetch market cap using SolanaTokenData
                $marketData = $this->tokenDataHelper->getTokenData($call->token_address);
//                var_dump($marketData);exit;

                if ($marketData === null) {
                    Log::warning("QuickNode API failed or token not indexed for {$call->token_address}, falling back to DexScreener");

                    // Fallback to DexScreener
                    $response = Http::timeout(5)->get("https://api.dexscreener.com/latest/dex/tokens/{$call->token_address}");
                    if (!$response->successful()) {
                        Log::warning("DexScreener API failed for {$call->token_address}", [
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);
                        $call->current_market_cap = 0;
                    } else {
                        $data = $response->json();
                        if (empty($data['pairs'][0])) {
                            Log::warning("No pairs found for {$call->token_address}", ['response' => $data]);
                            $call->current_market_cap = 0;
                        } else {
                            $marketCap = $data['pairs'][0]['marketCap'] ?? 0;
                            if ($marketCap <= 0) {
                                Log::warning("Invalid market cap for {$call->token_address}", ['marketCap' => $marketCap]);
                            }
                            $call->current_market_cap = $marketCap;
                        }
                    }
                } else {
                    $call->current_market_cap = $marketData['marketCap'] ?? 0;
                }

                // Log for debugging
                Log::debug("Market cap for {$call->token_address}", [
                    'current_market_cap' => $call->current_market_cap,
                    'buy_market_cap' => $call->market_cap,
                ]);
            } catch (\Exception $e) {
                Log::warning("Failed to fetch market cap for {$call->token_address}: {$e->getMessage()}", [
                    'exception' => $e->getTraceAsString(),
                ]);
                $call->current_market_cap = 0;
            }
        }

        return view('sniper.index', compact('solanaCalls'));
    }
}
