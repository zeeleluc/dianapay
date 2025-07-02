@if(!empty(config('cryptocurrencies.xrpl')))
    <ul class="space-y-2">
        @foreach(config('cryptocurrencies.xrpl') as $symbol => $data)
            <li>
                <a href="{{ url('articles/blockchain/xrpl/' . strtolower($symbol)) }}"
                   class="text-soft-yellow hover:underline font-semibold">
                    {{ $data['name'] ?? $symbol }} ({{ strtoupper($symbol) }})
                </a>
            </li>
        @endforeach
    </ul>
@endif
