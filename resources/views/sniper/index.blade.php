<x-guest-layout>
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
    <table class="w-full border-collapse border border-gray-700 text-white mb-8" id="open-positions-table">
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

                // Calculate unrealized profit using market cap
                $profitSol = '-';
                $profitPct = '-';
                if ($buyOrder && $call->market_cap > 0 && $call->current_market_cap > 0 && $buyOrder->amount_sol > 0 && $buyOrder->amount_foreign > 0) {
                    $priceRatio = $call->current_market_cap / $call->market_cap;
                    $profitSol = ($priceRatio * $buyOrder->amount_sol) - $buyOrder->amount_sol;
                    $profitPct = ($priceRatio - 1) * 100;
                    $profitSol = number_format($profitSol, 6);
                    $profitPct = number_format($profitPct, 2) . '%';
                } else {
                    \Illuminate\Support\Facades\Log::debug("Profit calculation skipped for SolanaCall ID {$call->id}", [
                        'market_cap' => $call->market_cap,
                        'current_market_cap' => $call->current_market_cap,
                        'amount_sol' => $buyOrder ? $buyOrder->amount_sol : null,
                        'amount_foreign' => $buyOrder ? $buyOrder->amount_foreign : null,
                    ]);
                }
            @endphp

            <tr class="hover:bg-gray-800 cursor-pointer" data-id="{{ $call->id }}" role="button" aria-expanded="false" aria-controls="details-{{ $call->id }}">
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
            <tr id="details-{{ $call->id }}" class="hidden">
                <td colspan="12" class="border border-gray-700 px-2 py-1 bg-gray-900 transition-all duration-300">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold mb-2">Order Details</h3>
                        @if($call->orders->isNotEmpty())
                            <table class="w-full border-collapse border border-gray-600 text-white">
                                <thead>
                                <tr class="bg-gray-700">
                                    <th class="border border-gray-600 px-2 py-1 text-left">ID</th>
                                    <th class="border border-gray-600 px-2 py-1 text-left">Type</th>
                                    <th class="border border-gray-600 px-2 py-1 text-right">Amount (Tokens)</th>
                                    <th class="border border-gray-600 px-2 py-1 text-right">Amount (SOL)</th>
                                    <th class="border border-gray-600 px-2 py-1 text-left">DEX Used</th>
                                    <th class="border border-gray-600 px-2 py-1 text-left">Error</th>
                                    <th class="border border-gray-600 px-2 py-1 text-left">Tx Signature</th>
                                    <th class="border border-gray-600 px-2 py-1 text-left">Created At</th>
                                    <th class="border border-gray-600 px-2 py-1 text-left">Updated At</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($call->orders as $order)
                                    <tr>
                                        <td class="border border-gray-600 px-2 py-1">{{ $order->id }}</td>
                                        <td class="border border-gray-600 px-2 py-1 capitalize">{{ $order->type }}</td>
                                        <td class="border border-gray-600 px-2 py-1 text-right">{{ number_format($order->amount_foreign, 8) }}</td>
                                        <td class="border border-gray-600 px-2 py-1 text-right">{{ number_format($order->amount_sol, 9) }}</td>
                                        <td class="border border-gray-600 px-2 py-1">{{ $order->dex_used ?: '-' }}</td>
                                        <td class="border border-gray-600 px-2 py-1">{{ $order->error ?: '-' }}</td>
                                        <td class="border border-gray-600 px-2 py-1">
                                            @if($order->tx_signature)
                                                <a target="_blank" class="underline" href="https://solscan.io/tx/{{ $order->tx_signature }}">
                                                    {{ \Illuminate\Support\Str::limit($order->tx_signature, 10, '…') }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="border border-gray-600 px-2 py-1">{{ $order->created_at->format('Y-m-d H:i:s') }}</td>
                                        <td class="border border-gray-600 px-2 py-1">{{ $order->updated_at->format('Y-m-d H:i:s') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="text-gray-400">No orders found.</p>
                        @endif
                    </div>
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

    {{-- JavaScript for toggling order details --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = document.getElementById('open-positions-table');
            const rows = table.querySelectorAll('tr[data-id]');

            rows.forEach(row => {
                row.addEventListener('click', function () {
                    const callId = this.getAttribute('data-id');
                    const detailsRow = document.getElementById(`details-${callId}`);
                    const isExpanded = !detailsRow.classList.contains('hidden');

                    // Collapse all other details rows
                    document.querySelectorAll('tr[id^="details-"]').forEach(otherRow => {
                        if (otherRow !== detailsRow) {
                            otherRow.classList.add('hidden');
                            const otherRowParent = otherRow.previousElementSibling;
                            if (otherRowParent) {
                                otherRowParent.setAttribute('aria-expanded', 'false');
                            }
                        }
                    });

                    // Toggle the clicked details row
                    detailsRow.classList.toggle('hidden');
                    this.setAttribute('aria-expanded', !isExpanded);
                });
            });
        });
    </script>

    {{-- CSS for smooth transition --}}
    <style>
        tr[id^="details-"] {
            transition: all 0.3s ease-in-out;
        }
        tr[id^="details-"].hidden {
            display: none;
        }
    </style>
</x-guest-layout>
