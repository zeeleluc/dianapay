@if(!empty(config('cryptocurrencies.polygon')))
    <ul class="space-y-2">
        @foreach(config('cryptocurrencies.polygon') as $symbol => $data)
            <li>
                <a href="{{ url('articles/blockchain/polygon/' . strtolower($symbol)) }}"
                   class="text-soft-yellow hover:underline font-semibold">
                    {{ $data['name'] ?? $symbol }} ({{ strtoupper($symbol) }})
                </a>
            </li>
        @endforeach
    </ul>
@endif
