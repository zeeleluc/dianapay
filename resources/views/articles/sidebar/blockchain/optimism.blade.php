@if(!empty(config('cryptocurrencies.optimism')))
    <ul class="space-y-2">
        @foreach(config('cryptocurrencies.optimism') as $symbol => $data)
            <li>
                <a href="{{ url('articles/blockchain/optimism/' . strtolower($symbol)) }}"
                   class="text-soft-yellow hover:underline font-semibold">
                    {{ $data['name'] ?? $symbol }} ({{ strtoupper($symbol) }})
                </a>
            </li>
        @endforeach
    </ul>
@endif
