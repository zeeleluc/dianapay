@props([
    'ratesByBlockchain' => [],
])

<div wire:poll.5s class="space-y-8 bg-gray-900 text-white p-6 rounded-xl shadow">
    @forelse ($ratesByBlockchain as $blockchain => $rates)
        <div>
            <h2 class="text-2xl font-semibold mb-4 capitalize">
                {{ $blockchain }} {{ __('Blockchain') }} {{ __('Rates') }}
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-sm bg-gray-800 border border-gray-700 rounded-lg">
                    <thead class="bg-gray-700">
                    <tr>
                        <th class="px-4 py-2 text-left border-b border-gray-600">Crypto</th>
                        @foreach ($rates['fiats'] as $fiat)
                            <th class="px-4 py-2 text-right border-b border-gray-600">{{ strtoupper($fiat) }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rates['cryptos'] as $crypto)
                        <tr class="hover:bg-gray-700">
                            <td class="px-4 py-2 border-b border-gray-700 font-medium">{{ $crypto }}</td>
                            @foreach ($rates['fiats'] as $fiat)
                                <td class="px-4 py-2 border-b border-gray-700 text-right text-xs">
                                    {{ isset($rates['data'][$crypto][$fiat])
                                        ? number_format($rates['data'][$crypto][$fiat], 8)
                                        : '-' }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p class="text-gray-400 text-sm">
            {{ __('No currency rates available.') }}
        </p>
    @endforelse
</div>
