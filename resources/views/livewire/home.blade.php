@php
    use App\Enums\CryptoEnum;

    $chains = collect(config('cryptocurrencies_styling'));
    $activeChains = $chains->filter(fn($c) => $c['active']);
    $inactiveChains = $chains->reject(fn($c) => $c['active']);
@endphp

<div>
    <div class="min-h-[calc(100vh-6rem)] flex flex-col justify-center items-center text-center px-4 sm:px-6
      bg-gradient-to-b from-black via-gray-900 to-gray-800 text-white"
    >
        <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold mb-4 drop-shadow-lg leading-tight">
            {{ config('app.name') }}
        </h1>

        <p class="text-base sm:text-lg md:text-xl mb-8 text-gray-300 max-w-xl">
            {{ translate('Crypto Payments — Easy, Fast, Global.') }}
        </p>

        <x-button href="{{ route('payment.anonymous.create') }}" variant="secondary" class="text-base sm:text-lg md:text-xl py-3 px-5 sm:py-4 sm:px-6">
            {{ translate('Create Anonymous Single Payment') }}
        </x-button>
    </div>

    <section class="bg-black bg-opacity-95 text-gray-100 py-16 px-6 sm:px-12 border-t border-gray-900 shadow-inner flex flex-col items-center">
        <div class="max-w-5xl w-full text-center">
            <h2 class="text-3xl sm:text-4xl font-bold mb-6">
                {{ translate('Supported Blockchains & Cryptocurrencies') }}
            </h2>

            <p class="mx-auto max-w-3xl text-gray-300 mb-12 text-lg sm:text-xl">
                {{ translate('We currently support the following blockchains and their cryptocurrencies. Enjoy seamless payments with a low fee of just 0.4% per monocurrency transaction.') }}
            </p>

            {{-- Active Chains --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8 mb-16">
                @foreach ($activeChains as $key => $chain)
                    @php
                        $cryptos = \App\Enums\CryptoEnum::forChain($key);
                    @endphp

                    <div class="rounded-xl p-6 shadow-lg border border-gray-800 bg-gray-900 text-center space-y-4">
                        <div class="inline-block px-3 py-1 rounded-full text-lg font-semibold text-white"
                             style="background: linear-gradient(to right, {{ $chain['color_primary'] }}, {{ $chain['color_secondary'] }});">
                            {{ strtoupper($chain['short_name']) }}
                        </div>

                        <h3 class="text-xl font-semibold">{{ $chain['long_name'] }}</h3>

                        {{-- Supported Cryptos --}}
                        @if (!empty($cryptos))
                            <div class="mt-4">
                                <div class="flex flex-wrap justify-center gap-2">
                                    @foreach ($cryptos as $crypto)
                                        @php
                                            $name = config("cryptocurrencies.{$crypto['chain']}.{$crypto['symbol']}.name") ?? $crypto['symbol'];
                                        @endphp
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium text-white border-none"
                                              style="background-color: {{ $chain['color_primary'] }};">
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
                <h3 class="text-2xl font-semibold text-gray-300 mb-6">{{ translate('Blockchains Coming Soon') }}</h3>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-6">
                    @foreach ($inactiveChains as $key => $chain)
                        <div class="rounded-lg bg-gray-800 px-4 pb-5 pt-7 shadow-md text-center flex flex-col items-center space-y-2">
                            <div class="inline-block px-3 py-1 rounded-full text-sm font-semibold text-white"
                                 style="background: linear-gradient(to right, {{ $chain['color_primary'] }}, {{ $chain['color_secondary'] }});">
                                {{ strtoupper($chain['short_name']) }}
                            </div>
                            <div class="text-sm font-medium text-gray-200 pt-2 block">
                                {{ $chain['long_name'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="bg-gray-900 text-gray-100 py-16 px-4 sm:px-6 md:px-12 border-t border-gray-700 shadow-inner">
        <div class="container mx-auto space-y-12">
            <h2 class="text-3xl sm:text-4xl font-bold text-center mb-10">{{ translate('Live Exchange Rates') }}</h2>
            <livewire:currency-rates-table />
        </div>
    </section>

</div>
