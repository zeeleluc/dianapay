<x-guest-layout>
    <h1 class="text-2xl font-bold mb-4 text-center">Solana Calls</h1>

    {{-- Total Profits (for closed positions) --}}
    <div class="mb-6 text-center">
        <div class="inline-block bg-gray-800 text-white px-6 py-3 rounded-lg shadow-md">
            <p class="text-lg font-semibold">
                Total Profit:
                <span class="{{ \App\Models\SolanaCall::totalProfitSol() < 0 ? 'text-red-400' : 'text-green-400' }}">
                    {{ \App\Models\SolanaCall::totalProfitSol() }} SOL
                </span>
                (<span class="{{ \App\Models\SolanaCall::totalProfitPercentage() < 0 ? 'text-red-400' : 'text-green-400' }}">
                    {{ \App\Models\SolanaCall::totalProfitPercentage() }}%
                </span>)
            </p>
        </div>
    </div>

    {{-- Open Positions Table --}}
    <h2 class="text-xl font-semibold mb-4">Open Positions</h2>
    <table class="w-full border-collapse border border-gray-700 text-white mb-8">
        <thead>
        <tr class="bg-gray-800">
            <th class="border border-gray-700 px-2 py-1 text-left">Name</th>
            <th class="border border-gray-700 px-2 py-1 text-left">Contract</th>
            <th class="border border-gray-700 px-2 py-1 text-left">Market Cap</th>
            <th class="border border-gray-700 px-2 py-1 text-left">Volume 24h</th>
            <th class="border border-gray-700 px-2 py-1 text-left">DS</th>
            <th class="border border-gray-700 px-2 py-1 text-left">DP</th>
            <th class="border border-gray-700 px-2 py-1 text-left">Strategy</th>
            <th class="border border-gray-700 px-2 py-1 text-center">Bought</th>
            <th class="border border-gray-700 px-2 py-1 text-center">Sold</th>
            <th class="border border-gray-700 px-2 py-1 text-center">Failures</th>
            <th class="border border-gray-700 px-2 py-1 text-right">Unrealized Profit (SOL)</th>
            <th class="border border-gray-700 px-2 py-1 text-right">Unrealized Profit (%)</th>
        </tr>
        </thead>
        <tbody>
        @php
            $openCalls = $solanaCalls->filter(function ($call) {
                return $call->orders->where('type', 'buy')->isNotEmpty() &&
                       $call->orders->where('type', 'sell')->isEmpty();
            });
        @endphp
        @foreach($openCalls as $call)
            @php
                $hasBuy = $call->orders->where('type', 'buy')->isNotEmpty();
                $hasSell = $call->orders->where('type', 'sell')->isNotEmpty();
                $failures = $call->orders->where('type', 'failed')->count();
                $buyOrder = $call->orders->where('type', 'buy')->first();

                // Fetch current price for unrealized profit
                $currentPrice = 0;
                $profitSol = '-';
                $profitPct = '-';
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(5)->get("https://api.dexscreener.com/latest/dex/tokens/{$call->token_address}");
                    if ($response->successful() && !empty($response->json('pairs'))) {
                        $currentPrice = $response->json('pairs.0.priceUsd') ?? 0;
                        if ($buyOrder && $buyOrder->price && $currentPrice > 0) {
                            $profitPct = (($currentPrice - $buyOrder->price) / $buyOrder->price) * 100;
                            $profitSol = ($currentPrice - $buyOrder->price) * $buyOrder->amount_foreign;
                            $profitPct = number_format($profitPct, 2) . '%';
                            $profitSol = number_format($profitSol, 6);
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("Failed to fetch price for {$call->token_address}: {$e->getMessage()}");
                }
            @endphp

            <tr class="hover:bg-gray-800">
                <td class="border border-gray-700 px-2 py-1">{{ $call->token_name }}</td>
                <td class="border border-gray-700 px-2 py-1">
                    <a target="_blank" class="underline" href="https://www.defined.fi/sol/{{ $call->token_address }}">
                        {{ \Illuminate\Support\Str::limit($call->token_address, 10, '…') }}
                    </a>
                </td>
                <td class="border border-gray-700 px-2 py-1">{{ number_format($call->market_cap, 0) }}</td>
                <td class="border border-gray-700 px-2 py-1">{{ number_format($call->volume_24h, 0) }}</td>
                <td class="border border-gray-700 px-2 py-1">{{ $call->dev_sold ? 'Y' : 'N' }}</td>
                <td class="border border-gray-700 px-2 py-1">{{ $call->dex_paid ? 'Y' : 'N' }}</td>
                <td class="border border-gray-700 px-2 py-1">{{ $call->strategy ?: '-' }}</td>
                <td class="border border-gray-700 px-2 py-1 text-center">
                    <span class="text-green-500 font-semibold">✓</span>
                </td>
                <td class="border border-gray-700 px-2 py-1 text-center">
                    <span class="text-red-500 font-semibold">✗</span>
                </td>
                <td class="border border-gray-700 px-2 py-1 text-center">{{ $failures }}</td>
                <td class="border border-gray-700 px-2 py-1 text-right {{ $profitSol !== '-' && $profitSol < 0 ? 'text-red-400' : 'text-green-400' }}">
                    {{ $profitSol }}
                </td>
                <td class="border border-gray-700 px-2 py-1 text-right {{ $profitPct !== '-' && $profitPct < 0 ? 'text-red-400' : 'text-green-400' }}">
                    {{ $profitPct }}
                </td>
            </tr>
        @endforeach
        @if($openCalls->isEmpty())
            <tr>
                <td colspan="12" class="border border-gray-700 px-2 py-1 text-center">No open positions</td>
            </tr>
        @endif
        </tbody>
    </table>

    {{-- Closed Positions Table --}}
    <h2 class="text-xl font-semibold mb-4">Closed Positions</h2>
    <table class="w-full border-collapse border border-gray-700 text-white">
        <thead>
        <tr class="bg-gray-800">
            <th class="border border-gray-700 px-2 py-1 text-left">Name</th>
            <th class="border border-gray-700 px-2 py-1 text-left">Contract</th>
            <th class="border border-gray-700 px-2 py-1 text-left">Market Cap</th>
            <th class="border border-gray-700 px-2 py-1 text-left">Volume 24h</th>
            <th class="border border-gray-700 px-2 py-1 text-left">DS</th>
            <th class="border border-gray-700 px-2 py-1 text-left">DP</th>
            <th class="border border-gray-700 px-2 py-1 text-left">Strategy</th>
            <th class="border border-gray-700 px-2 py-1 text-center">Bought</th>
            <th class="border border-gray-700 px-2 py-1 text-center">Sold</th>
            <th class="border border-gray-700 px-2 py-1 text-center">Failures</th>
            <th class="border border-gray-700 px-2 py-1 text-right">Realized Profit (SOL)</th>
            <th class="border border-gray-700 px-2 py-1 text-right">Realized Profit (%)</th>
        </tr>
        </thead>
        <tbody>
        @php
            $closedCalls = $solanaCalls->filter(function ($call) {
                return $call->orders->where('type', 'buy')->isNotEmpty() &&
                       $call->orders->where('type', 'sell')->isNotEmpty();
            });
        @endphp
        @foreach($closedCalls as $call)
            @php
                $hasBuy = $call->orders->where('type', 'buy')->isNotEmpty();
                $hasSell = $call->orders->where('type', 'sell')->isNotEmpty();
                $failures = $call->orders->where('type', 'failed')->count();
                $profitSol = $hasBuy && $hasSell ? number_format($call->profit(), 6) : '-';
                $profitPct = $hasBuy && $hasSell ? $call->profitPercentage().'%' : '-';
            @endphp

            <tr class="hover:bg-gray-800">
                <td class="border border-gray-700 px-2 py-1">{{ $call->token_name }}</td>
                <td class="border border-gray-700 px-2 py-1">
                    <a target="_blank" class="underline" href="https://www.defined.fi/sol/{{ $call->token_address }}">
                        {{ \Illuminate\Support\Str::limit($call->token_address, 10, '…') }}
                    </a>
                </td>
                <td class="border border-gray-700 px-2 py-1">{{ number_format($call->market_cap, 0) }}</td>
                <td class="border border-gray-700 px-2 py-1">{{ number_format($call->volume_24h, 0) }}</td>
                <td class="border border-gray-700 px-2 py-1">{{ $call->dev_sold ? 'Y' : 'N' }}</td>
                <td class="border border-gray-700 px-2 py-1">{{ $call->dex_paid ? 'Y' : 'N' }}</td>
                <td class="border border-gray-700 px-2 py-1">{{ $call->strategy ?: '-' }}</td>
                <td class="border border-gray-700 px-2 py-1 text-center">
                    <span class="text-green-500 font-semibold">✓</span>
                </td>
                <td class="border border-gray-700 px-2 py-1 text-center">
                    <span class="text-green-500 font-semibold">✓</span>
                </td>
                <td class="border border-gray-700 px-2 py-1 text-center">{{ $failures }}</td>
                <td class="border border-gray-700 px-2 py-1 text-right {{ $profitSol !== '-' && $profitSol < 0 ? 'text-red-400' : 'text-green-400' }}">
                    {{ $profitSol }}
                </td>
                <td class="border border-gray-700 px-2 py-1 text-right {{ $profitPct !== '-' && $call->profitPercentage() < 0 ? 'text-red-400' : 'text-green-400' }}">
                    {{ $profitPct }}
                </td>
            </tr>
        @endforeach
        @if($closedCalls->isEmpty())
            <tr>
                <td colspan="12" class="border border-gray-700 px-2 py-1 text-center">No closed positions</td>
            </tr>
        @endif
        </tbody>
    </table>
</x-guest-layout>
