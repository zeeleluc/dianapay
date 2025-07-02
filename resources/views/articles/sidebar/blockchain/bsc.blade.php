@if(!empty(config('cryptocurrencies.bsc')))
    <ul class="space-y-2">
        @foreach(config('cryptocurrencies.bsc') as $symbol => $data)
            <li>
                <a href="{{ url('articles/blockchain/bsc/' . strtolower($symbol)) }}"
                   class="text-soft-yellow hover:underline font-semibold">
                    {{ $data['name'] ?? $symbol }} ({{ strtoupper($symbol) }})
                </a>
            </li>
        @endforeach
    </ul>
@endif
