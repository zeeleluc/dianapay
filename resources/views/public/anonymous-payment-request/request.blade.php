@php
    $fiat = strtolower($paymentRequest->fiat);
    $symbols = config('fiats', []);
    $symbol = $symbols[$fiat] ?? strtoupper($fiat);
    $amount = number_format($paymentRequest->amount_minor / (10 ** \App\Enums\FiatEnum::decimalsFor($paymentRequest->fiat)), 2);
    $description = trim($paymentRequest->description);

    $lines = [];

    $lines[] = translate('Hi!');
    $lines[] = translate("I'd like to request a payment of :currency:amount in cryptocurrency.", [
        'amount' => $amount,
        'currency' => $symbol,
    ]);

    if ($description) {
        $lines[] = translate('Description: ":description"', ['description' => $description]);
    }

    $lines[] = translate("You can make the payment securely using this link:");
    $lines[] = $showUrl;
    $lines[] = translate("Thank you!");

    $message = implode(PHP_EOL . PHP_EOL, $lines);
    $encodedMessage = urlencode($message);
@endphp

<x-layouts.homepage>

    <x-wavy-section>
        {!! translate('Share Your Anonymous Cryptocurrency Payment Request') !!}
    </x-wavy-section>

    <div class="w-full bg-darker lg:max-w-xl lg:mx-auto mt-10 p-8 rounded-lg text-gray-100 flex flex-col items-center text-center space-y-8 mb-14">

        <div class="text-4xl font-semibold">
            {{ $symbol }}
            {{ number_format($paymentRequest->amount_minor / (10 ** \App\Enums\FiatEnum::decimalsFor($paymentRequest->fiat)), 2) }}
        </div>

        <div class="bg-dark rounded-lg p-6 shadow-inner text-gray-300 max-w-xl w-full">
            {{ $paymentRequest->description }}
        </div>

        <div class="flex flex-wrap justify-center gap-4 max-w-xl w-full">
            {{-- Signal --}}
            <a href="sgnl://send?text={{ $encodedMessage }}"
               class="px-4 py-2 bg-dark rounded hover:bg-gray-600 transition text-white font-semibold"
               title="Signal">
                    Signal
            </a>

            {{-- WhatsApp --}}
            <a href="https://wa.me/?text={{ $encodedMessage }}" target="_blank" rel="noopener noreferrer"
               class="px-4 py-2 bg-dark rounded hover:bg-gray-600 transition text-white font-semibold"
               title="WhatsApp">
                    WhatsApp
            </a>

            {{-- Email --}}
            <a href="mailto:?subject={{ urlencode(translate('Payment Request')) }}&body={{ $encodedMessage }}"
               class="px-4 py-2 bg-dark rounded hover:bg-gray-600 transition text-white font-semibold"
               title="Email">
                    E-mail
            </a>
        </div>

        {{-- QR Code linking to the show page --}}
        <a href="{{ $showUrl }}" target="_blank" rel="noopener noreferrer" class="block max-w-xs mx-auto">
            <img src="{{ $qrUrl }}" alt="{{ translate('Scan to Pay') }}" class="rounded-lg shadow-lg" />
        </a>

        {{-- Status and timestamps --}}
        <div class="text-sm text-gray-400 space-y-1 max-w-xl w-full">
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

        <div class="flex flex-wrap justify-center gap-4 max-w-xl w-full">
            <x-button href="{{ $showUrl }}" class="flex-1 min-w-[140px] text-center">
                {{ translate('Go to Payment Page') }}
            </x-button>

            <x-button variant="secondary" href="{{ $createUrl }}" class="flex-1 min-w-[140px] text-center">
                {{ translate('Create Another Payment Request') }}
            </x-button>
        </div>


    </div>
</x-layouts.homepage>
