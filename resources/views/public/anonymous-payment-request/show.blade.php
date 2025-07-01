@php
    $fiat = strtolower($paymentRequest->fiat);
    $symbols = config('fiats', []);
    $symbol = $symbols[$fiat] ?? strtoupper($fiat);
@endphp

<x-layouts.clean>
    <div class="w-full p-8 bg-gray-900 rounded-lg shadow-lg text-gray-100 flex flex-col items-center text-center">
        <h1 class="text-3xl font-extrabold mb-6">{{ translate('Please Pay Me') }}</h1>

        <div class="mb-8 text-3xl font-semibold">
            {{ $symbol }}
            {{ number_format($paymentRequest->amount_minor / (10 ** \App\Enums\FiatEnum::decimalsFor($paymentRequest->fiat)), 2) }}
        </div>

        <div class="mb-8 bg-gray-800 rounded-lg p-4 shadow-inner text-gray-300 max-w-xl w-full">
            {{ $paymentRequest->description }}
        </div>

        <div class="text-sm text-gray-400 space-y-1 mb-8 max-w-xl w-full">
            <p>
                <strong>{{ translate('Status') }}:</strong>
                @if(strtolower($paymentRequest->status) === 'pending')
                    <span class="inline-flex items-center space-x-2 justify-center">
                        <span class="w-3 h-3 bg-orange-500 rounded-full"></span>
                        <svg class="w-4 h-4 text-orange-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span>{{ ucfirst($paymentRequest->status) }}</span>
                    </span>
                @else
                    {{ ucfirst($paymentRequest->status) }}
                @endif
            </p>

            <p>
                <strong>{{ translate('Created At') }}:</strong> {{ $paymentRequest->created_at->toDayDateTimeString() }}
                <sup>UTC</sup>
            </p>

            @if($paymentRequest->paid_at)
                <p>
                    <strong>{{ translate('Paid At') }}:</strong> {{ $paymentRequest->paid_at->toDayDateTimeString() }}
                    <sup>UTC</sup>
                </p>
            @endif
        </div>

        <button
            type="button"
            disabled
            class="w-full py-3 px-4 bg-gray-700 text-gray-400 rounded-md cursor-not-allowed border border-gray-600 hover:bg-gray-600 transition-colors max-w-xl"
        >
            {{ translate('Connect Cryptowallet') }}
        </button>

    </div>
</x-layouts.clean>
