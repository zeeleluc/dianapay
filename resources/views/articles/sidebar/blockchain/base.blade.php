@if(!empty(config('cryptocurrencies.base')))
    <ul class="space-y-2">
        @foreach(config('cryptocurrencies.base') as $symbol => $data)
            <li>
                <a href="{{ url('articles/blockchain/base/' . strtolower($symbol)) }}"
                   class="text-soft-yellow hover:underline font-semibold">
                    {{ $data['name'] ?? $symbol }} ({{ strtoupper($symbol) }})
                </a>
            </li>
        @endforeach
    </ul>
@endif
