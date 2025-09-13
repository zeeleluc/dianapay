<x-guest-layout>
    <h1 class="text-2xl font-bold mb-4">Solana Calls</h1>

    @foreach($solanaCalls as $call)
        <div class="mb-6 p-4 bg-gray-900 rounded shadow">
            <h2 class="font-semibold text-lg">{{ $call->token_name }} ({{ $call->token_address }})</h2>
            <p>Market Cap: {{ $call->market_cap }}</p>
            <p>Volume 24h: {{ $call->volume_24h }}</p>
            <p>Profit (SOL): {{ $call->profit() }}</p>

            @if($call->orders->count() > 0)
                <table class="w-full mt-4 border-collapse border border-gray-700 text-white">
                    <thead>
                    <tr>
                        <th class="border border-gray-700 px-2 py-1">Type</th>
                        <th class="border border-gray-700 px-2 py-1">Amount (Foreign)</th>
                        <th class="border border-gray-700 px-2 py-1">Amount (SOL)</th>
                        <th class="border border-gray-700 px-2 py-1">DEX Used</th>
                        <th class="border border-gray-700 px-2 py-1">TX Signature</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($call->orders as $order)
                        <tr>
                            <td class="border border-gray-700 px-2 py-1">{{ $order->type }}</td>
                            <td class="border border-gray-700 px-2 py-1">{{ $order->amount_foreign }}</td>
                            <td class="border border-gray-700 px-2 py-1">{{ $order->amount_sol }}</td>
                            <td class="border border-gray-700 px-2 py-1">{{ $order->dex_used }}</td>
                            <td class="border border-gray-700 px-2 py-1">{{ $order->tx_signature }}</td>
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
