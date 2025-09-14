<x-guest-layout>
    <h1 class="text-2xl font-bold mb-4 text-center">Solana Calls</h1>

    {{-- Total Profits --}}
    <div class="mb-6 text-center">
        <div class="inline-block bg-gray-800 text-white px-6 py-3 rounded-lg shadow-md">
            <p class="text-lg font-semibold">
                Total Profit:
                <span class="text-green-400">{{ \App\Models\SolanaCall::totalProfitSol() }} SOL</span>
                (<span class="text-green-400">{{ \App\Models\SolanaCall::totalProfitPercentage() }}%</span>)
            </p>
        </div>
    </div>

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
            <th class="border border-gray-700 px-2 py-1 text-right">Profit (SOL)</th>
            <th class="border border-gray-700 px-2 py-1 text-right">Profit (%)</th>
        </tr>
        </thead>
        <tbody>
        @foreach($solanaCalls as $call)
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

                {{-- Bought / Sold status --}}
                <td class="border border-gray-700 px-2 py-1 text-center">
                    @if($hasBuy)
                        <span class="text-green-500 font-semibold">✓</span>
                    @else
                        <span class="text-red-500 font-semibold">✗</span>
                    @endif
                </td>
                <td class="border border-gray-700 px-2 py-1 text-center">
                    @if($hasSell)
                        <span class="text-green-500 font-semibold">✓</span>
                    @else
                        <span class="text-red-500 font-semibold">✗</span>
                    @endif
                </td>

                {{-- Failures count --}}
                <td class="border border-gray-700 px-2 py-1 text-center">{{ $failures }}</td>

                {{-- Profit --}}
                <td class="border border-gray-700 px-2 py-1 text-right">{{ $profitSol }}</td>
                <td class="border border-gray-700 px-2 py-1 text-right">{{ $profitPct }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-guest-layout>
