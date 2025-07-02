@if(!empty(config('cryptocurrencies.ethereum')))
    <ul class="space-y-2">
        @foreach(config('cryptocurrencies.ethereum') as $symbol => $data)
            <li>
                <a href="{{ url('articles/blockchain/ethereum/' . strtolower($symbol)) }}"
                   class="text-soft-yellow hover:underline font-semibold">
                    {{ $data['name'] ?? $symbol }} ({{ strtoupper($symbol) }})
                </a>
            </li>
        @endforeach
    </ul>
@endif
