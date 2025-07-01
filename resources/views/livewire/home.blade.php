@php
    use App\Enums\CryptoEnum;
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
        <div class="max-w-5xl w-full space-y-8 text-center">
            <h2 class="text-3xl sm:text-4xl font-bold mb-6">
                {{ translate('Supported Blockchains & Cryptocurrencies') }}
            </h2>

            <p class="mx-auto max-w-3xl text-gray-300 mb-8 text-lg sm:text-xl">
                {{ translate('We currently support the following blockchains and their cryptocurrencies. Enjoy seamless payments with a low fee of just 0.4% per monocurrency transaction.') }}
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
                @foreach (CryptoEnum::grouped() as $chain => $cryptos)
                    <div class="bg-gray-900 rounded-lg p-6 shadow-lg flex flex-col items-center">
                        <h3 class="text-xl font-semibold mb-4">{{ strtoupper($chain) }}</h3>
                        <div class="flex flex-wrap justify-center gap-3">
                            @foreach ($cryptos as $crypto)
                                <span
                                    class="bg-blue-600 text-white rounded-full px-4 py-1 text-sm font-medium"
                                    title="{{ $crypto['symbol'] }}"
                                >
                                {{ strtoupper($crypto['symbol']) }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
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
