<ul class="space-y-2">
    @php
        $pages = [
            'about' => 'About',
            'pricing' => 'Pricing',
            'terms' => 'Terms',
            'privacy' => 'Privacy',
            'faq' => 'FAQ',
            'contact' => 'Contact'
        ];
    @endphp
    @foreach($pages as $slug => $label)
        @php
            $isActive = request()->is(app()->getLocale() . '/' . $slug);
        @endphp
        <li>
            @if ($isActive)
                <span class="font-semibold text-white cursor-default">
                    {{ translate($label) }}
                </span>
            @else
                <a href="{{ url(app()->getLocale() . '/' . $slug) }}"
                   class="font-semibold text-soft-yellow hover:underline">
                    {{ translate($label) }}
                </a>
            @endif
        </li>
    @endforeach
</ul>
