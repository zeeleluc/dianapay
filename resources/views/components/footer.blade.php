<footer class="bg-dark text-gray-300 py-12 px-6 sm:px-12">

    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-8">

        {{-- About / Logo --}}
        <div>
            <a class="text-white" href="/" wire:navigate>
                <x-application-logo class="w-20 h-20 text-white" />
            </a>
            <p class="text-gray-400 text-sm leading-relaxed max-w-xs">
                {{ translate(':appName provides seamless blockchain payment solutions with low fees and fast transactions. Join thousands of happy customers worldwide.', ['appName' => config('app.name')]) }}
            </p>
        </div>

        {{-- Quick Links --}}
        <div>
            <h3 class="text-white font-semibold mb-4">{{ translate('Quick Links') }}</h3>
            <ul class="space-y-2 text-sm">
                <li><a href="{{ url('/') }}" class="hover:text-white transition">{{ translate('Home') }}</a></li>
                <li><a href="{{ url('/about') }}" class="hover:text-white transition">{{ translate('About Us') }}</a></li>
                <li><a href="{{ url('/pricing') }}" class="hover:text-white transition">{{ translate('Pricing') }}</a></li>
                <li><a href="{{ url('/faq') }}" class="hover:text-white transition">{{ translate('FAQ') }}</a></li>
                <li><a href="{{ url('/contact') }}" class="hover:text-white transition">{{ translate('Contact') }}</a></li>
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
                            {{ translate($chainName) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Social + Newsletter --}}
        <div>
            <h3 class="text-white font-semibold mb-4">{{ translate('Stay Connected') }}</h3>
            <div class="flex space-x-4 mb-6">
                <a href="https://twitter.com/yourcompany" target="_blank" class="hover:text-white transition" aria-label="{{ translate('Twitter') }}">
                    <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24" ><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg>
                </a>
                <a href="https://facebook.com/yourcompany" target="_blank" class="hover:text-white transition" aria-label="{{ translate('Facebook') }}">
                    <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 5 3.66 9.13 8.44 9.88v-6.99h-2.54v-2.89h2.54V9.5c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.23.2 2.23.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.87h2.78l-.44 2.89h-2.34v6.99C18.34 21.13 22 17 22 12z"/></svg>
                </a>
                <a href="https://linkedin.com/company/yourcompany" target="_blank" class="hover:text-white transition" aria-label="{{ translate('LinkedIn') }}">
                    <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-4 0v7h-4v-7a6 6 0 016-6zM2 9h4v12H2zM4 3a2 2 0 110 4 2 2 0 010-4z"/></svg>
                </a>
            </div>
        </div>
    </div>

    <div class="mt-12 border-t border-gray-800 pt-6 text-center text-gray-500 text-sm">
        &copy; {{ date('Y') }} {{ config('app.name') }}. {{ translate('All rights reserved.') }} |
        <a href="{{ url('/terms') }}" class="hover:text-white">{{ translate('Terms') }}</a> |
        <a href="{{ url('/privacy') }}" class="hover:text-white">{{ translate('Privacy Policy') }}</a>
    </div>
</footer>
