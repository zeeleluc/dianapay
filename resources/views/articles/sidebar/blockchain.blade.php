<ul class="space-y-2">
    @foreach(collect(config('cryptocurrencies_styling'))->sortKeys() as $chain => $chainData)
        <li>
            <a href="{{ url('articles/blockchain/' . $chain) }}"
               class="text-soft-yellow hover:underline font-semibold">
                {{ ucfirst($chainData['long_name']) }}
            </a>
        </li>
    @endforeach
</ul>
