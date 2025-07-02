@if(!empty(config('cryptocurrencies.avalanche')))
    <ul class="space-y-2">
        @foreach(config('cryptocurrencies.avalanche') as $symbol => $data)
            <li>
                <a href="{{ url('articles/blockchain/avalanche/' . strtolower($symbol)) }}"
                   class="text-soft-yellow hover:underline font-semibold">
                    {{ $data['name'] ?? $symbol }} ({{ strtoupper($symbol) }})
                </a>
            </li>
        @endforeach
    </ul>
@endif
