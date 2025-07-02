<ul class="space-y-2">
    @foreach(config('cryptocurrencies.base') as $symbol => $data)
        @php
            $isActive = strtolower($slug3 ?? '') === strtolower($symbol);
            $label = ($data['name'] ?? $symbol) . ' (' . strtoupper($symbol) . ')';
        @endphp
        <li>
            @if ($isActive)
                <span class="font-semibold text-white cursor-default">
                    {{ $label }}
                </span>
            @else
                <a href="{{ url('articles/blockchain/base/' . strtolower($symbol)) }}"
                   class="font-semibold text-soft-yellow hover:underline">
                    {{ $label }}
                </a>
            @endif
        </li>
    @endforeach
</ul>
