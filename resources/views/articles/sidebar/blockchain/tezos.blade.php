@if(!empty(config('cryptocurrencies.tezos')))
    <ul class="space-y-2">
        @foreach(config('cryptocurrencies.tezos') as $symbol => $data)
            <li>
                <a href="{{ url('articles/blockchain/tezos/' . strtolower($symbol)) }}"
                   class="text-soft-yellow hover:underline font-semibold">
                    {{ $data['name'] ?? $symbol }} ({{ strtoupper($symbol) }})
                </a>
            </li>
        @endforeach
    </ul>
@endif
