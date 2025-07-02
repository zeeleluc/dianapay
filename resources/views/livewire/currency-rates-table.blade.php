@props([
    'ratesByBlockchain' => [],
])

<div wire:poll.keep-alive class="space-y-8 bg-gray-950 text-white p-6 rounded-xl shadow">
    @forelse ($ratesByBlockchain as $blockchain => $rates)
        <div>
            <h2 class="text-2xl font-semibold mb-4 capitalize">
                {{ $blockchain }} {{ translate('Blockchain') }} {{ translate('Rates') }}
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-sm bg-gray-950 border-t border-b border-gray-700 rounded-lg">
                    <thead class="bg-gray-950">
                    <tr>
                        <th class="py-2 text-left border-b border-gray-600 pl-0">Crypto</th>
                        @foreach ($rates['fiats'] as $fiat)
                            <th class="px-4 py-2 text-right border-b border-gray-600 {{ $loop->last ? 'pr-0' : '' }}">
                                {{ strtoupper($fiat) }}
                            </th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rates['cryptos'] as $crypto)
                        <tr>
                            <td class="py-2 border-b border-gray-700 font-medium pl-0">{{ $crypto }}</td>
                            @foreach ($rates['fiats'] as $fiat)
                                <td class="py-2 border-b border-gray-700 text-right text-xs {{ $loop->last ? 'pr-0' : 'px-4' }}">
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
