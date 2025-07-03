<footer class="bg-dark text-gray-300 pb-12 px-6 sm:px-12">

    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-8 mt-14">

        {{-- About / Logo --}}
        <div>
            <a class="text-white" href="/" wire:navigate>
                <x-application-logo class="w-20 h-20 text-white" />
            </a>
            <p class="text-gray-400 mt-5 text-sm leading-relaxed max-w-xs">
                <strong>
                    Crypt Me Up
                </strong>
                <br />
                {{ translate('We provide seamless blockchain payment solutions with low fees and fast transactions.') }}
            </p>
        </div>

        {{-- Quick Links --}}
        <div>
            <h3 class="text-white font-semibold mb-4">{{ translate('Quick Links') }}</h3>
            <ul class="space-y-2 text-sm">
                <li><a href="{{ url('/') }}" class="hover:text-white transition">{{ translate('Main Page') }}</a></li>
                <li><a href="{{ url(get_locale() . '/about') }}" class="hover:text-white transition">{{ translate('About Us') }}</a></li>
                <li><a href="{{ url(get_locale() . '/pricing') }}" class="hover:text-white transition">{{ translate('Pricing') }}</a></li>
                <li><a href="{{ url(get_locale() . '/faq') }}" class="hover:text-white transition">{{ translate('FAQ') }}</a></li>
                <li><a href="{{ url(get_locale() . '/contact') }}" class="hover:text-white transition">{{ translate('Contact') }}</a></li>
            </ul>
        </div>

        {{-- Supported Chains --}}
        <div>
            <h3 class="text-white font-semibold mb-4">{{ translate('Supported Blockchains') }}</h3>
            <ul class="space-y-2 text-sm max-h-48 overflow-auto scrollbar-thin scrollbar-thumb-gray-700">
                @foreach(\App\Enums\CryptoEnum::allChains() as $chainKey)
                    @php
                        $chain = config("chains.{$chainKey}");
                        $chainName = $chain['long_name'] ?? ucfirst($chainKey);
                        $chainUrl = route('articles.show', ['locale' => get_locale(), 'slug1' => 'blockchain', 'slug2' => $chainKey]);
                    @endphp
                    <li>
                        <a href="{{ $chainUrl }}" class="hover:text-white transition">
                            {{ $chainName }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Social + Newsletter --}}
        <div>
            <h3 class="text-white font-semibold mb-4">{{ translate('Stay Connected') }}</h3>
            <div class="flex space-x-4 mb-6">
                <a href="https://twitter.com/getcryptmeup" target="_blank" class="hover:text-white transition" aria-label="X">
                    X
                </a>
            </div>
        </div>
    </div>
</footer>

<x-wavy-section>
    {!! translate('Simplify Crypto Payments') !!}
</x-wavy-section>

<div class="bg-dark text-gray-300 pb-12 px-6 sm:px-12">

    <div class="max-w-5xl mx-auto md:grid-cols-4 text-center text-base mt-5">
        This tool facilitates crypto payments between users. No fiat currency exchange takes place. The amounts shown in fiat are for value indication purposes only. We do not hold any funds and do not operate as a financial service provider.
        <strong>Crypt Me Up</strong> is a product of Zeelebosch Curaçao B.V., a company registered in Curaçao.
    </div>

    <div class="mt-2 pt-2 text-center text-gray-500 text-sm">
        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved. |
        <a href="{{ url(get_locale() . '/terms') }}" class="hover:text-white">Terms</a> |
        <a href="{{ url(get_locale() . '/privacy') }}" class="hover:text-white">Privacy Policy</a>
    </div>
</div>
