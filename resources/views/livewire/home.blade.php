@php
    use App\Enums\CryptoEnum;

    $chains = collect(config('cryptocurrencies_styling'));
    $activeChains = $chains->filter(fn($c) => $c['active']);
    $inactiveChains = $chains->reject(fn($c) => $c['active']);
@endphp

<div>
    <div class="relative bg-dark text-white overflow-hidden">
        <div class="max-w-6xl mx-auto flex flex-col justify-center items-center text-center pt-12 pb-24 px-4 sm:px-6">
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold mb-4 drop-shadow-lg leading-tight">
                {!! translate('Simplify Your Crypto Transactions') !!}
            </h1>
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold mb-4 drop-shadow-lg leading-tight">
                {!! translate('Start Receiving Payments Today') !!}
            </h1>
        </div>

        <!-- Full-width bottom wave with multiple curls -->
        <div class="absolute bottom-0 left-0 w-full leading-[0]">
            <svg class="relative block w-full h-[160px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320" preserveAspectRatio="none">
                <path fill="#0F1114" d="M0,256 C180,192 360,320 540,256 C720,192 900,320 1080,256 C1260,192 1440,320 1440,320 L1440,320 L0,320 Z"></path>
            </svg>
        </div>
    </div>

    <section class="pt-24 bg-darker text-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-7xl">
            <h2 class="text-5xl font-bold mb-14 text-center">{{ translate('Our Products & Features') }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
                <!-- Feature Card 1 -->
                <div class="bg-dark rounded-lg shadow-md p-6 flex flex-col justify-between">
                    <h3 class="text-xl font-semibold mb-3">{!! translate('Anonymous Crypto Payments') !!}</h3>
                    <p class="text-gray-400 mb-6">
                    {!! translate('Quickly create a simple crypto payment request anyone can pay — no account needed.')  !!}
                    </p>

                    <ul class="mb-6 text-gray-300 space-y-1 text-sm">
                        <li>{{ translate('0.4% transaction fee') }}</li>
                        <li>{!! translate('No bridging, crypto-to-crypto payments') !!}</li>
                        <li>{!! translate('Monocurrency payments') !!}</li>
                    </ul>

                    <x-button href="{{ route('payment.anonymous.create') }}" variant="secondary" class="text-base sm:text-lg py-3 px-5">
                        {{ translate('Create Payment') }}
                    </x-button>
                </div>

                <!-- Feature Card 2 -->
                <div class="bg-dark rounded-lg shadow-md p-6 flex flex-col justify-between opacity-70 cursor-not-allowed">
                    <h3 class="text-xl font-semibold mb-3">{!! translate('Custom Payment Buttons') !!}</h3>
                    <p class="text-gray-400 mb-6">
                        {!! translate('Prepare payments on our platform for various products, then embed payment buttons or links on your website.') !!}
                    </p>

                    <ul class="mb-6 text-gray-300 space-y-1 text-sm">
                        <li>{!! translate('0.4% transaction fee') !!}</li>
                        <li>{{ translate('Create and customize payment links') }}</li>
                        <li>{{ translate('Easy embedding on any site') }}</li>
                    </ul>

                    <x-button disabled class="text-base sm:text-lg py-3 px-5 cursor-not-allowed" variant="secondary">
                        {{ translate('Coming Soon') }}
                    </x-button>
                </div>

                <!-- Feature Card 3 -->
                <div class="bg-dark rounded-lg shadow-md p-6 flex flex-col justify-between opacity-70 cursor-not-allowed">
                    <h3 class="text-xl font-semibold mb-3">{!! translate('E-Commerce Plugin Integrations') !!}</h3>
                    <p class="text-gray-400 mb-6">
                        {!! translate('Seamless integration with major webshop platforms like WooCommerce and Shopify, for smooth crypto payments.') !!}
                    </p>

                    <ul class="mb-6 text-gray-300 space-y-1 text-sm">
                        <li>{{ translate('0.4% transaction fee') }}</li>
                        <li>{{ translate('Supports WooCommerce, Shopify, and more') }}</li>
                        <li>{{ translate('Easy setup and management') }}</li>
                    </ul>

                    <x-button disabled class="text-base sm:text-lg py-3 px-5 cursor-not-allowed" variant="secondary">
                        {{ translate('Coming Soon') }}
                    </x-button>
                </div>
            </div>
        </div>
    </section>


    <div class="bg-darker text-white py-10 px-4 sm:px-6 shadow-xl">
        <div class="w-full lg:max-w-screen-lg mx-auto text-gray-100 my-20">
            <h1 class="text-4xl sm:text-5xl font-extrabold mb-6 text-center">
                {!! translate('The Future of') !!}
                <span class="text-yellow-300">
                    {!! translate('Crypto Payments') !!}
                </span>
            </h1>

            <p class="max-w-4xl mx-auto text-xl sm:text-3xl text-gray-300 leading-relaxed mb-8 text-center">
                {!! translate('We are pioneering the next generation of cryptocurrency payment solutions, designed with simplicity, security, and scalability in mind. Our mission is to empower businesses and individuals to accept crypto payments seamlessly, without the hassle of complicated setups or costly intermediaries.') !!}
            </p>

            <p class="max-w-3xl mx-auto text-md sm:text-2xl text-gray-400 leading-relaxed mb-10 text-center">
                {!! translate('We have successfully launched an anonymous payment solution — enabling instant, secure transactions with a low fee of only 0.4%. Currently supporting a select range of cryptocurrencies on the Base blockchain, our platform operates without swapping or bridging, ensuring your funds move directly from crypto to crypto every time.') !!}
            </p>

            <p class="max-w-3xl mx-auto text-md sm:text-2xl text-gray-400 leading-relaxed mb-12 text-center">
                {!! translate('This is just the beginning. Soon, we will offer account-based features, allowing you to integrate effortlessly with popular platforms like WooCommerce and Shopify. You’ll also gain access to detailed analytics right from your dashboard, helping you track your sales, revenue, and customer behavior — all in one place.') !!}
            </p>
        </div>
    </div>

    <section class="bg-dark bg-opacity-95 text-gray-100 py-16 px-6 sm:px-12 border-t border-gray-900 shadow-inner flex flex-col items-center">
        <div class="max-w-5xl w-full text-center">
            <h2 class="text-3xl sm:text-4xl font-bold mb-6">
                {!! translate('Supported Blockchains & Cryptocurrencies') !!}
            </h2>

            <p class="mx-auto max-w-3xl text-gray-300 mb-12 text-lg sm:text-xl">
                {!! translate('We currently support the following blockchains and their cryptocurrencies. Enjoy seamless payments with a low fee of just 0.4% per monocurrency transaction.') !!}
            </p>

            {{-- Active Chains --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8 mb-16">
                @foreach ($activeChains as $key => $chain)
                    @php
                        $cryptos = CryptoEnum::forChain($key);
                    @endphp

                    <div class="rounded-xl p-6 bg-darker text-center space-y-4">
                        <div class="inline-block px-3 py-1 rounded-full text-2xl font-semibold text-white"
                             style="background: {{ $chain['color_primary'] }}">
                            {{ strtoupper($chain['short_name']) }}
                        </div>

                        <h3 class="text-xl font-semibold">{{ $chain['long_name'] }}</h3>

                        @if (!empty($cryptos))
                            <div class="mt-4">
                                <div class="flex flex-wrap justify-center gap-2">
                                    @foreach ($cryptos as $crypto)
                                        @php
                                            $name = config("cryptocurrencies.{$crypto['chain']}.{$crypto['symbol']}.name") ?? $crypto['symbol'];
                                        @endphp
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium text-white border-none"
                                              style="background: {{ $chain['color_primary'] }}">
                                            {{ $name }} ({{ $crypto['symbol'] }})
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Coming Soon --}}
            <div class="border-t border-gray-700 pt-12">
                <h3 class="text-2xl font-semibold text-gray-300 mb-12">{!! translate('Blockchains Coming Soon') !!}</h3>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-6">
                    @foreach ($inactiveChains as $key => $chain)
                        <div class="rounded-lg bg-darker px-4 pb-5 pt-7 text-center flex flex-col items-center space-y-2">
                            <div class="inline-block px-3 py-1 rounded-full text-sm font-semibold text-white"
                                 style="background: {{ $chain['color_primary'] }}">
                                {{ strtoupper($chain['short_name']) }}
                            </div>
                            <div class="text-base font-medium text-gray-200 pt-2 block">
                                {{ $chain['long_name'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="bg-dark text-gray-100 py-16 px-4 sm:px-6 md:px-12">
        <div class="container mx-auto space-y-12">
            <h2 class="text-3xl sm:text-4xl font-bold text-center mb-10">{!! translate('Live Exchange Rates') !!}</h2>
            <livewire:currency-rates-table />
        </div>
    </section>
</div>
