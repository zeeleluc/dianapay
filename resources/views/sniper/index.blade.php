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
            {{-- existing rows --}}
        @endforeach
        </tbody>
    </table>
</x-guest-layout>
