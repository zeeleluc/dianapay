@if(!empty(config('cryptocurrencies.stellar')))
    <ul class="space-y-2">
        @foreach(config('cryptocurrencies.stellar') as $symbol => $data)
            <li>
                <a href="{{ url('articles/blockchain/stellar/' . strtolower($symbol)) }}"
                   class="text-soft-yellow hover:underline font-semibold">
                    {{ $data['name'] ?? $symbol }} ({{ strtoupper($symbol) }})
                </a>
            </li>
        @endforeach
    </ul>
@endif
