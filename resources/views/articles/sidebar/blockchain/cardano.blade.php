@if(!empty(config('cryptocurrencies.cardano')))
    <ul class="space-y-2">
        @foreach(config('cryptocurrencies.cardano') as $symbol => $data)
            <li>
                <a href="{{ url('articles/blockchain/cardano/' . strtolower($symbol)) }}"
                   class="text-soft-yellow hover:underline font-semibold">
                    {{ $data['name'] ?? $symbol }} ({{ strtoupper($symbol) }})
                </a>
            </li>
        @endforeach
    </ul>
@endif
