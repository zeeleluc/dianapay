<x-guest-layout>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <h1 class="text-2xl font-bold mb-4 text-center">Solana Calls</h1>

    {{-- Total Profits (for closed positions) --}}
    <div class="mb-6 text-center">
        <div class="inline-block bg-gray-800 text-white px-6 py-3 rounded-lg shadow-md">
            <p class="text-lg font-semibold">
                Total Profit Closed Positions:
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
    <div class="overflow-x-auto">
    <table class="min-w-full border-collapse border border-gray-700 text-white text-sm md:text-base" id="open-positions-table">
        <thead>
        <tr class="bg-gray-800">
            <th class="border border-gray-700 px-2 py-1 text-right text-xs">Unrealized Profit (%)</th>
            <th class="border border-gray-700 px-2 py-1 text-center text-xs">Bought At</th>
            <th class="border border-gray-700 px-2 py-1 text-left text-xs">Name</th>
            <th class="border border-gray-700 px-2 py-1 text-left text-xs">Contract</th>
            <th class="border border-gray-700 px-2 py-1 text-left text-xs">Market Cap</th>
            <th class="border border-gray-700 px-2 py-1 text-left text-xs">DS</th>
            <th class="border border-gray-700 px-2 py-1 text-left text-xs">DP</th>
            <th class="border border-gray-700 px-2 py-1 text-left text-xs">Strategy</th>
            <th class="border border-gray-700 px-2 py-1 text-left text-xs">Buy</th>
            <th class="border border-gray-700 px-2 py-1 text-center text-xs">Failures</th>
            <th class="border border-gray-700 px-2 py-1 text-center text-xs"></th>
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
            @endphp

            <tr class="hover:bg-gray-800 cursor-pointer" data-id="{{ $call->id }}" role="button" aria-expanded="false" aria-controls="details-{{ $call->id }}">
                <td class="border border-gray-700 px-2 py-1 text-right {{ $call->unrealized_profit_sol !== '-' && $call->unrealized_profit_sol < 0 ? 'text-red-400' : 'text-green-400' }} text-xs">
                    {{ $call->unrealized_profit_sol }}%
                </td>
                <td class="border border-gray-700 px-2 py-1 text-center text-xs">
                    @if($buyOrder)
                        {{ $buyOrder->created_at->diffForHumans() }}
                    @else
                        -
                    @endif
                </td>
                <td class="border border-gray-700 px-2 py-1 text-xs">{{ $call->token_name }}</td>
                <td class="border border-gray-700 px-2 py-1 text-xs">
                    <a target="_blank" class="underline" href="https://dexscreener.com/solana/{{ $call->token_address }}">
                        {{ \Illuminate\Support\Str::limit($call->token_address, 10, '…') }}
                    </a>
                </td>
                <td class="border border-gray-700 px-2 py-1 text-xs">
                    {{ human_readable_number($call->market_cap) }}
                    /
                    {{ human_readable_number($call->current_market_cap) }}
                </td>
                <td class="border border-gray-700 px-2 py-1 text-xs">{{ $call->dev_sold ? 'Y' : 'N' }}</td>
                <td class="border border-gray-700 px-2 py-1 text-xs">{{ $call->dex_paid ? 'Y' : 'N' }}</td>
                <td class="border border-gray-700 px-2 py-1 text-xs">{{ $call->strategy ?: '-' }}</td>
                <td class="border border-gray-700 px-2 py-1 text-center text-xs">
                    @if($call->reason_buy)
                        <button
                            onclick="openReasonModal('buy', '{{ $call->reason_buy }}')"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs">
                            Reason
                        </button>
                    @else
                        -
                    @endif
                </td>
                <td class="border border-gray-700 px-2 py-1 text-center text-xs">{{ $failures }}</td>
                <td class="border border-gray-700 px-2 py-1 text-center text-xs">
                    <button
                        onclick="toggleDetails({{ $call->id }})"
                        class="bg-gray-600 hover:bg-gray-700 text-white px-2 py-1 rounded text-xs">
                        Details
                    </button>
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
                                    <th class="border border-gray-600 px-2 py-1 text-left text-xs">ID</th>
                                    <th class="border border-gray-600 px-2 py-1 text-left text-xs">Type</th>
                                    <th class="border border-gray-600 px-2 py-1 text-right text-xs">Amount (Tokens)</th>
                                    <th class="border border-gray-600 px-2 py-1 text-right text-xs">Amount (SOL)</th>
                                    <th class="border border-gray-600 px-2 py-1 text-left text-xs">DEX Used</th>
                                    <th class="border border-gray-600 px-2 py-1 text-left text-xs">Error</th>
                                    <th class="border border-gray-600 px-2 py-1 text-left text-xs">Tx Signature</th>
                                    <th class="border border-gray-600 px-2 py-1 text-left text-xs">Created At</th>
                                    <th class="border border-gray-600 px-2 py-1 text-left text-xs">Updated At</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($call->orders as $order)
                                    <tr>
                                        <td class="border border-gray-600 px-2 py-1 text-xs">{{ $order->id }}</td>
                                        <td class="border border-gray-600 px-2 py-1 capitalize text-xs">{{ $order->type }}</td>
                                        <td class="border border-gray-600 px-2 py-1 text-right text-xs">{{ number_format($order->amount_foreign, 8) }}</td>
                                        <td class="border border-gray-600 px-2 py-1 text-right text-xs">{{ number_format($order->amount_sol, 9) }}</td>
                                        <td class="border border-gray-600 px-2 py-1 text-xs">{{ $order->dex_used ?: '-' }}</td>
                                        <td class="border border-gray-600 px-2 py-1 text-xs">{{ $order->error ?: '-' }}</td>
                                        <td class="border border-gray-600 px-2 py-1 text-xs">
                                            @if($order->tx_signature)
                                                <a target="_blank" class="underline" href="https://solscan.io/tx/{{ $order->tx_signature }}">
                                                    {{ \Illuminate\Support\Str::limit($order->tx_signature, 10, '…') }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="border border-gray-600 px-2 py-1 text-xs">{{ $order->created_at->format('Y-m-d H:i:s') }}</td>
                                        <td class="border border-gray-600 px-2 py-1 text-xs">{{ $order->updated_at->format('Y-m-d H:i:s') }}</td>
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
                <td colspan="12" class="border border-gray-700 px-2 py-1 text-center text-xs">No open positions</td>
            </tr>
        @endif
        </tbody>
    </table>
    </div>

    {{-- Closed Positions Table --}}
    <h2 class="text-xl font-semibold mb-4 mt-6">Closed Positions</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full border-collapse border border-gray-700 text-white text-sm md:text-base" id="open-positions-table">
        <thead>
        <tr class="bg-gray-800">
            <th class="border border-gray-700 px-2 py-1 text-right text-xs">Realized Profit (SOL)</th>
            <th class="border border-gray-700 px-2 py-1 text-right text-xs">Realized Profit (%)</th>
            <th class="border border-gray-700 px-2 py-1 text-center text-xs">Bought At</th>
            <th class="border border-gray-700 px-2 py-1 text-center text-xs">Duration</th>
            <th class="border border-gray-700 px-2 py-1 text-left text-xs">Name</th>
            <th class="border border-gray-700 px-2 py-1 text-left text-xs">Contract</th>
            <th class="border border-gray-700 px-2 py-1 text-left text-xs">Market Cap</th>
            <th class="border border-gray-700 px-2 py-1 text-left text-xs">DS</th>
            <th class="border border-gray-700 px-2 py-1 text-left text-xs">DP</th>
            <th class="border border-gray-700 px-2 py-1 text-left text-xs">Strategy</th>
            <th class="border border-gray-700 px-2 py-1 text-center text-xs">Buy</th>
            <th class="border border-gray-700 px-2 py-1 text-center text-xs">Sell</th>
            <th class="border border-gray-700 px-2 py-1 text-center text-xs">Failures</th>
        </tr>
        </thead>
        <tbody>
        @php
            $closedCalls = $solanaCalls->filter(function ($call) {
                return $call->orders->where('type', 'buy')->isNotEmpty() &&
                       $call->orders->where('type', 'sell')->isNotEmpty();
            })->sortByDesc(function ($call) {
                $sellOrder = $call->orders->where('type', 'sell')->first();
                return $sellOrder ? $sellOrder->created_at->timestamp : 0;
            });
        @endphp
        @foreach($closedCalls as $call)
            @php
                $buyOrder = $call->orders->where('type', 'buy')->first();
                $sellOrder = $call->orders->where('type', 'sell')->first();
                $hasBuy = $call->orders->where('type', 'buy')->isNotEmpty();
                $hasSell = $call->orders->where('type', 'sell')->isNotEmpty();
                $failures = $call->orders->where('type', 'failed')->count();
                $profitSol = $hasBuy && $hasSell ? number_format($call->profit(), 6) : '-';
                $profitPct = $hasBuy && $hasSell ? $call->profitPercentage().'%' : '-';
                $sellOrder = $call->orders->where('type', 'sell')->first();
            @endphp

            <tr class="hover:bg-gray-800 cursor-pointer" data-id="{{ $call->id }}" role="button" aria-expanded="false" aria-controls="details-{{ $call->id }}">
                <td class="border border-gray-700 px-2 py-1 text-right {{ $profitSol !== '-' && $profitSol < 0 ? 'text-red-400' : 'text-green-400' }} text-xs">
                    {{ number_format($profitSol, 6) }}
                </td>
                <td class="border border-gray-700 px-2 py-1 text-right {{ $profitPct !== '-' && $call->profitPercentage() < 0 ? 'text-red-400' : 'text-green-400' }} text-xs">
                    {{ $profitPct }}
                </td>
                <td class="border border-gray-700 px-2 py-1 text-center text-xs">
                    {{ $buyOrder ? $buyOrder->created_at->diffForHumans() : '-' }}
                </td>
                <td class="border border-gray-700 px-2 py-1 text-center text-xs">
                    {{ $buyOrder && $sellOrder ? $buyOrder->created_at->diffForHumans($sellOrder->created_at, true) : '-' }}
                </td>
                <td class="border border-gray-700 px-2 py-1 text-xs">{{ $call->token_name }}</td>
                <td class="border border-gray-700 px-2 py-1 text-xs">
                    <a target="_blank" class="underline" href="https://dexscreener.com/solana/{{ $call->token_address }}">
                        {{ \Illuminate\Support\Str::limit($call->token_address, 10, '…') }}
                    </a>
                </td>
                <td class="border border-gray-700 px-2 py-1 text-xs">{{ human_readable_number($call->market_cap) }}</td>
                <td class="border border-gray-700 px-2 py-1 text-xs">{{ $call->dev_sold ? 'Y' : 'N' }}</td>
                <td class="border border-gray-700 px-2 py-1 text-xs">{{ $call->dex_paid ? 'Y' : 'N' }}</td>
                <td class="border border-gray-700 px-2 py-1 text-xs">{{ $call->strategy ?: '-' }}</td>
                <td class="border border-gray-700 px-2 py-1 text-center text-xs">
                    @if($call->reason_buy)
                        <button
                            onclick="openReasonModal('buy', '{{ $call->reason_buy }}')"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs">
                            Reason
                        </button>
                    @else
                        -
                    @endif
                </td>

                <td class="border border-gray-700 px-2 py-1 text-center text-xs">
                    @if($call->reason_sell)
                        <button
                            onclick="openReasonModal('sell', '{{ $call->reason_sell }}')"
                            class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs">
                            Reason
                        </button>
                    @else
                        -
                    @endif
                </td>
                <td class="border border-gray-700 px-2 py-1 text-center text-xs">{{ $failures }}</td>
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
        @if($closedCalls->isEmpty())
            <tr>
                <td colspan="12" class="border border-gray-700 px-2 py-1 text-center">No closed positions</td>
            </tr>
        @endif
        </tbody>
    </table>
    </div>

    <div id="reason-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-gray-900 text-white rounded-lg p-6 w-96 relative">
            <h3 id="reason-modal-title" class="text-lg font-semibold mb-4"></h3>
            <p id="reason-modal-content" class="text-sm"></p>
            <button onclick="closeReasonModal()" class="absolute top-2 right-2 text-white font-bold">&times;</button>
        </div>
    </div>

    <script>
        function openReasonModal(type, reason) {
            const modal = document.getElementById('reason-modal');
            const title = document.getElementById('reason-modal-title');
            const content = document.getElementById('reason-modal-content');

            title.textContent = type === 'buy' ? 'Buy Reason' : 'Sell Reason';
            content.textContent = reason;

            modal.classList.remove('hidden');
        }

        function closeReasonModal() {
            document.getElementById('reason-modal').classList.add('hidden');
        }
    </script>

    <script>
        function toggleDetails(callId) {
            const detailsRow = document.getElementById(`details-${callId}`);
            const isExpanded = !detailsRow.classList.contains('hidden');

            // Collapse all other details rows
            document.querySelectorAll('tr[id^="details-"]').forEach(otherRow => {
                if (otherRow !== detailsRow) {
                    otherRow.classList.add('hidden');
                }
            });

            // Toggle the clicked details row
            detailsRow.classList.toggle('hidden');
        }
    </script>

    <style>
        tr[id^="details-"] {
            transition: all 0.3s ease-in-out;
        }
        tr[id^="details-"].hidden {
            display: none;
        }
    </style>

    <script>
        // Auto-refresh the page every 60 seconds (60000 milliseconds)
        setInterval(function () {
            window.location.reload();
        }, 60000);
    </script>
</x-guest-layout>
