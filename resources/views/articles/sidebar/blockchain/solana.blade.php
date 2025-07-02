@if(!empty(config('cryptocurrencies.solana')))
    <ul class="space-y-2">
        @foreach(config('cryptocurrencies.solana') as $symbol => $data)
            <li>
                <a href="{{ url('articles/blockchain/solana/' . strtolower($symbol)) }}"
                   class="text-soft-yellow hover:underline font-semibold">
                    {{ $data['name'] ?? $symbol }} ({{ strtoupper($symbol) }})
                </a>
            </li>
        @endforeach
    </ul>
@endif
