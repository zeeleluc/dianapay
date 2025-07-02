@if(!empty(config('cryptocurrencies.linea')))
    <ul class="space-y-2">
        @foreach(config('cryptocurrencies.linea') as $symbol => $data)
            <li>
                <a href="{{ url('articles/blockchain/linea/' . strtolower($symbol)) }}"
                   class="text-soft-yellow hover:underline font-semibold">
                    {{ $data['name'] ?? $symbol }} ({{ strtoupper($symbol) }})
                </a>
            </li>
        @endforeach
    </ul>
@endif
