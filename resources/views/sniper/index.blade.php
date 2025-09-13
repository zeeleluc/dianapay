<x-guest-layout>
    <h1 class="text-2xl font-bold mb-4 text-center">Solana Calls</h1>

    <!-- Total profits summary -->
    <div class="mb-6 p-4 bg-gray-800 rounded shadow text-white text-center">
        <p class="text-2xl font-semibold">Total Profit (SOL): {{ \App\Models\SolanaCall::totalProfitSol() }}</p>
        <p class="text-2xl font-semibold">Total Profit (%): {{ \App\Models\SolanaCall::totalProfitPercentage() }}%</p>
    </div>

    @foreach($solanaCalls as $call)
        <div class="mb-6 p-4 bg-gray-900 rounded shadow">
            <h2 class="font-semibold text-lg">{{ $call->token_name }}</h2>
            <h3 class="font-semibold text-lg">
                <a target="_blank" class="underline" href="https://www.defined.fi/sol/{{ $call->token_address }}">
                    {{ \Illuminate\Support\Str::limit($call->token_address, 10, '…') }}
                </a>
            </h3>

            <p>Market Cap: {{ number_format($call->market_cap, 0)}}</p>
            <p>Volume 24h: {{ number_format($call->volume_24h, 0) }}</p>
            <p>Profit (SOL): {{ number_format($call->profit(), 6) }}</p>
            <p>Profit (%): {{ $call->profitPercentage() }}%</p>
            <p>Dev Sold: {{ $call->dev_sold ? 'Y' : 'N' }}</p>
            <p>Dex Paid: {{ $call->dex_paid ? 'Y' : 'N' }}</p>
            <p>Strategy: {{ $call->strategy ?: '-' }}</p>

            @if($call->orders->count() > 0)
                <table class="w-full mt-4 border-collapse border border-gray-700 text-white">
                    <thead>
                    <tr>
                        <th class="border border-gray-700 px-2 py-1 text-left">Type</th>
                        <th class="border border-gray-700 px-2 py-1 text-left">Amount (Foreign)</th>
                        <th class="border border-gray-700 px-2 py-1 text-left">Amount (SOL)</th>
                        <th class="border border-gray-700 px-2 py-1 text-left">DEX Used</th>
                        <th class="border border-gray-700 px-2 py-1 text-left">TX Signature</th>
                        <th class="border border-gray-700 px-2 py-1 text-left">Error</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($call->orders as $order)
                        <tr>
                            <td class="border border-gray-700 px-2 py-1">{{ $order->type }}</td>
                            <td class="border border-gray-700 px-2 py-1">{{ number_format($order->amount_foreign, 0) }}</td>
                            <td class="border border-gray-700 px-2 py-1">{{ number_format($order->amount_sol, 6) }}</td>
                            <td class="border border-gray-700 px-2 py-1">{{ $order->dex_used }}</td>
                            <td class="border border-gray-700 px-2 py-1">
                                {{ \Illuminate\Support\Str::limit($order->tx_signature, 10, '…') }}
                            </td>
                            <td class="border border-gray-700 px-2 py-1">
                                {{ $order->error }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <p class="mt-2 text-gray-400">No related orders.</p>
            @endif
        </div>
    @endforeach
</x-guest-layout>
