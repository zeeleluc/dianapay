@props([
    'ratesByBlockchain' => [],
])

<div wire:poll.5s class="space-y-8 bg-gray-900 text-gray-100 p-4 rounded-xl">
    @forelse ($ratesByBlockchain as $blockchain => $rates)
        <div>
            <h2 class="text-2xl font-bold mb-4 capitalize">{{ $blockchain }} {{ translate('Blockchain') }} {{ translate('Rates') }}</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-gray-800 border border-gray-700 rounded">
                    <thead>
                    <tr class="bg-gray-700 text-gray-100">
                        <th class="px-4 py-2 border-b border-gray-600 text-left"></th>
                        @foreach ($rates['fiats'] as $fiat)
                            <th class="px-4 py-2 border-b border-gray-600 text-right text-sm">{{ strtoupper($fiat) }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rates['cryptos'] as $crypto)
                        <tr class="hover:bg-gray-700">
                            <td class="px-4 py-2 border-b border-gray-700 font-medium text-sm">{{ $crypto }}</td>
                            @foreach ($rates['fiats'] as $fiat)
                                <td class="px-4 py-2 border-b border-gray-700 text-right text-xs">
                                    @if (isset($rates['data'][$crypto][$fiat]))
                                        {{ number_format($rates['data'][$crypto][$fiat], 8) }}
                                    @else
                                        <span class="text-gray-500">-</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p class="text-gray-400">
            {{ translate('No currency rates available.') }}
        </p>
    @endforelse
</div>
