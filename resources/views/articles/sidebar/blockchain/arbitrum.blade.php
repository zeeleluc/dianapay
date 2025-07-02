@if(!empty(config('cryptocurrencies.arbitrum')))
    <ul class="space-y-2">
        @foreach(config('cryptocurrencies.arbitrum') as $symbol => $data)
            <li>
                <a href="{{ url('articles/blockchain/arbitrum/' . strtolower($symbol)) }}"
                   class="text-soft-yellow hover:underline font-semibold">
                    {{ $data['name'] ?? $symbol }} ({{ strtoupper($symbol) }})
                </a>
            </li>
        @endforeach
    </ul>
@endif
