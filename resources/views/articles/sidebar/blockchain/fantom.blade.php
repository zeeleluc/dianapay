@if(!empty(config('cryptocurrencies.fantom')))
    <ul class="space-y-2">
        @foreach(config('cryptocurrencies.fantom') as $symbol => $data)
            <li>
                <a href="{{ url('articles/blockchain/fantom/' . strtolower($symbol)) }}"
                   class="text-soft-yellow hover:underline font-semibold">
                    {{ $data['name'] ?? $symbol }} ({{ strtoupper($symbol) }})
                </a>
            </li>
        @endforeach
    </ul>
@endif
