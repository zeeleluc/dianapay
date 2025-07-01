@php
    $fiat = strtolower($anonymousPaymentRequest->fiat);
    $symbols = config('fiats', []);
    $symbol = $symbols[$fiat] ?? strtoupper($fiat);
@endphp

<div class="w-full p-8 bg-gray-900 rounded-lg shadow-lg text-gray-100 flex flex-col items-center text-center">
    <h1 class="text-3xl font-extrabold mb-6">{{ translate('Please Pay Me') }}</h1>

    <div class="mb-8 text-3xl font-semibold">
        {{ $symbol }}{{ number_format($anonymousPaymentRequest->amount_minor / (10 ** \App\Enums\FiatEnum::decimalsFor($anonymousPaymentRequest->fiat)), 2) }}
    </div>

    <div class="mb-8 bg-gray-800 rounded-lg p-4 shadow-inner text-gray-300 max-w-xl w-full">
        {{ $anonymousPaymentRequest->description }}
    </div>

    <div class="text-sm text-gray-400 space-y-1 mb-8 max-w-xl w-full">
        <p>
            <strong>{{ translate('Status') }}:</strong>
            @if(strtolower($anonymousPaymentRequest->status) === 'pending')
                <span class="inline-flex items-center space-x-2 justify-center">
                    <span class="w-3 h-3 bg-orange-500 rounded-full"></span>
                    <svg class="w-4 h-4 text-orange-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span>{{ ucfirst($anonymousPaymentRequest->status) }}</span>
                </span>
            @else
                {{ ucfirst($anonymousPaymentRequest->status) }}
            @endif
        </p>

        <p>
            <strong>{{ translate('Created At') }}:</strong> {{ $anonymousPaymentRequest->created_at->toDayDateTimeString() }}
            <sup>UTC</sup>
        </p>

        @if($anonymousPaymentRequest->paid_at)
            <p>
                <strong>{{ translate('Paid At') }}:</strong> {{ $anonymousPaymentRequest->paid_at->toDayDateTimeString() }}
                <sup>UTC</sup>
            </p>
        @endif
    </div>

    <div class="mb-8 w-full max-w-xl space-y-4">
        <!-- Progress bar -->
        <div class="h-3 w-full bg-gray-700 rounded-full overflow-hidden">
            <div class="h-full bg-yellow-400 transition-all duration-300"
                 style="width: {{ $step * (100 / 3) }}%">
            </div>
        </div>

        <!-- Single step block -->
        <div class="p-6 rounded-lg border-2 transition
            {{ $step === 1 ? 'border-yellow-400 bg-yellow-100 text-yellow-800' : '' }}
            {{ $step === 2 ? 'border-yellow-400 bg-yellow-100 text-yellow-800' : '' }}
            {{ $step === 3 ? 'border-yellow-400 bg-yellow-100 text-yellow-800' : '' }}">

            <h2 class="text-xl font-bold mb-2">Step {{ $step }}</h2>

            @if ($step === 1)
                <p class="text-sm mb-4">{{ translate('Choose Blockchain') }}</p>

                <div class="grid grid-cols-2 sm:grid-cols-2 gap-4">
                    @foreach($this->chains as $chain)
                        <label
                            wire:click="$set('selectedChain', '{{ $chain }}')"
                            class="cursor-pointer p-1 rounded-lg border-2 text-center font-semibold transition
                                {{ $selectedChain === $chain ? 'border-yellow-500 bg-yellow-200 text-yellow-900' : 'border-gray-400 bg-white text-gray-700' }}"
                        >
                            {{ ucfirst($chain) }}
                        </label>
                    @endforeach
                </div>
            @elseif($step === 2)
                <p class="text-sm mb-4">{{ translate('Choose Cryptocurrency') }}</p>

                @if (!$selectedChain)
                    <div class="text-red-600 text-sm">
                        {{ translate('Please select a blockchain first.') }}
                    </div>
                @elseif (empty($this->availableCryptos))
                    <div class="text-gray-500 text-sm">
                        {{ translate('No cryptocurrencies available for this blockchain.') }}
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-2 gap-4">
                        @foreach($this->availableCryptos as $crypto)
                            <label
                                wire:click="$set('selectedCrypto', '{{ $crypto['symbol'] }}')"
                                class="cursor-pointer p-1 rounded-lg border-2 text-center font-semibold transition
                                    {{ $selectedCrypto === $crypto['symbol'] ? 'border-yellow-500 bg-yellow-200 text-yellow-900' : 'border-gray-400 bg-white text-gray-700' }}"
                            >
                                {{ strtoupper($crypto['symbol']) }}
                            </label>
                        @endforeach
                    </div>
                @endif

            @elseif($step === 3)
                <div wire:poll.5s="updateCryptoAmount">
                    <p class="text-sm mb-2 font-medium">
                        {{ translate('Connect Cryptowallet and pay:') }}
                    </p>

                    @if ($cryptoAmount)
                        <div class="text-2xl font-bold text-orange-800 mb-2">
                            {{ number_format($cryptoAmount, 8) }}
                            <br />
                            {{ strtoupper($selectedCrypto) }}
                        </div>
                    @else
                        <div class="text-sm text-red-400">
                            {{ translate('No rate available for this selection.') }}
                        </div>
                    @endif
                </div>
            @endif

        </div>

        <!-- Step controls -->
        <div class="flex justify-between">
            <button wire:click="previousStep"
                    class="px-4 py-2 bg-gray-700 text-gray-100 rounded-md hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                @disabled($step === 1)>
                {{ translate('Previous') }}
            </button>

            <button wire:click="nextStep"
                    class="px-4 py-2 bg-yellow-500 text-gray-900 font-bold rounded-md hover:bg-yellow-400 disabled:opacity-50 disabled:cursor-not-allowed"
                @disabled(
                    $step === 3 ||
                    ($step === 1 && !$selectedChain) ||
                    ($step === 2 && !$selectedCrypto)
                )>
                {{ translate('Next') }}
            </button>
        </div>
    </div>

    @if($step === 3)
        <button
            type="button"
            class="w-full py-3 px-4 bg-yellow-500 text-gray-900 rounded-md border border-yellow-400 hover:bg-yellow-400 transition-colors max-w-xl font-semibold"
        >
            {{ translate('Connect Cryptowallet') }}
        </button>
    @else
        <button
            type="button"
            disabled
            class="w-full py-3 px-4 bg-gray-700 text-gray-400 rounded-md cursor-not-allowed border border-gray-600 hover:bg-gray-600 transition-colors max-w-xl"
        >
            {{ translate('Connect Cryptowallet') }}
        </button>
    @endif
</div>
