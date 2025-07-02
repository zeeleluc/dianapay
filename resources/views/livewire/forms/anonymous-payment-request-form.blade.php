@php
    $chains = config('cryptocurrencies_styling');
    $evmChains = collect($chains)->filter(fn($chain) => $chain['evm'] && $chain['active'])->pluck('long_name')->toArray();
@endphp

<div>
    <x-wavy-section>
        {!! translate('Create Your Anonymous Cryptocurrency Payment Request') !!}
    </x-wavy-section>

    <div class="flex items-start justify-center gap-8 mt-12 max-w-5xl mx-auto text-gray-100">
        @php
            // This makes them statically detectable by your string extractor
            $steps = [
                'step1' => translate('Submit anonymous payment request'),
                'step2' => translate('Share payment with the payee'),
                'step3' => translate('Payee pays you in the cryptocurrency you want'),
            ];
        @endphp

        @foreach (array_values($steps) as $index => $text)
            <div class="flex flex-col items-center w-72 min-h-[220px] text-center">
                <div class="w-14 h-14 rounded-full bg-soft-blue flex items-center justify-center text-white font-extrabold text-3xl">
                    {{ $index + 1 }}
                </div>
                <span class="mt-4 text-2xl font-extrabold max-w-[16rem]">
                {!! $text !!}
            </span>
            </div>
        @endforeach
    </div>

    <div class="max-w-md mx-auto bg-darker p-6 rounded-lg text-gray-100 mb-10">
        @if (session()->has('message'))
            <div class="mb-4 bg-green-700 text-green-100 p-3 rounded shadow">
                {{ translate(session('message')) }}
            </div>
        @endif

        <form wire:submit.prevent="submit" class="space-y-6">
            <div>
                <label for="fiat" class="block mb-2 font-semibold text-gray-300">
                    {{ translate('Fiat Currency') }}
                </label>

                <x-dropdown align="left" width="48" :contentClasses="'py-2 bg-dark text-white max-h-60 overflow-auto'">
                    <x-slot name="trigger">
                        <button type="button" class="w-full text-left px-3 py-2 rounded bg-dark text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            {{ $fiat ? strtoupper($fiat) : translate('-- Select Fiat --') }}
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        @foreach ($fiats as $fiatOption)
                            <button
                                type="button"
                                class="block w-full text-left px-4 py-2 hover:bg-gray-700 focus:bg-gray-700 focus:outline-none"
                                wire:click="$set('fiat', '{{ $fiatOption }}')"
                            >
                                {{ strtoupper($fiatOption) }}
                            </button>
                        @endforeach
                    </x-slot>
                </x-dropdown>

                @error('fiat') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="amount" class="block mb-2 font-semibold text-gray-300">{{ translate('Amount') }}</label>
                <div class="flex space-x-2 items-center">
                    <x-text-input
                        wire:model.defer="amount"
                        id="amount"
                        type="number"
                        min="0"
                        step="{{ $this->hasMinorAmount ? '1' : 'any' }}"
                        class="flex-grow"
                    />
                    @if ($this->hasMinorAmount)
                        <x-text-input
                            wire:model.defer="amount_minor"
                            id="amount_minor"
                            type="number"
                            min="0"
                            max="{{ str_repeat('9', \App\Enums\FiatEnum::decimalsFor($fiat)) }}"
                            step="1"
                            class="w-16 text-center"
                            placeholder="{{ str_repeat('0', \App\Enums\FiatEnum::decimalsFor($fiat)) }}"
                        />
                    @endif
                </div>
                @error('amount') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                @error('amount_minor') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="acceptedChains" class="block mb-2 font-semibold text-gray-300">
                    {!! translate('Select the cryptocurrencies and blockchains you accept') !!}
                </label>
                @foreach (\App\Enums\CryptoEnum::grouped() as $chain => $cryptos)
                    <div class="mb-4 bg-dark p-3 rounded">
                        <label class="cursor-pointer inline-flex items-center font-bold text-lg tracking-wide text-gray-300 bg-gray-800 border-none rounded transition-all duration-200 hover:bg-gray-700 [&>input:checked+span]:bg-blue-700 [&>input:checked+span]:text-white [&>input:checked+span]:border-blue-700">
                            <input
                                type="checkbox"
                                wire:model="acceptedChains"
                                value="{{ $chain }}"
                                wire:change="syncCryptoSelection('{{ $chain }}')"
                                class="hidden"
                                wire:loading.attr="disabled"
                            >
                            <span class="rounded px-3 py-1 border border-gray-600 uppercase">{{ $chain }}</span>
                        </label>

                        @if (in_array($chain, $acceptedChains))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($cryptos as $crypto)
                                    <label class="cursor-pointer bg-gray-800 text-gray-300 border-none rounded px-0 py-0 text-sm transition-all duration-200 hover:bg-gray-700 flex items-center [&>input:checked+span]:bg-blue-600 [&>input:checked+span]:text-white [&>input:checked+span]:border-blue-600">
                                        <input
                                            type="checkbox"
                                            wire:model="acceptedCryptoSelection"
                                            value="{{ $crypto['symbol'] }}"
                                            class="hidden"
                                            wire:loading.attr="disabled"
                                        >
                                        <span class="rounded px-2 py-1 border border-gray-600">{{ $crypto['symbol'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
                @error('acceptedCryptoSelection')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- EVM Wallet for Ethereum, Polygon, Base, BNB, etc --}}
            @if (count(array_intersect(['base', 'polygon', 'bnb', 'ethereum'], $acceptedChains)) > 0)
                <div>
                    <label for="to_wallet_evm" class="block mb-2 font-semibold text-gray-300">
                        {{ translate('To') }} EVM {{ translate('Cryptowallet') }}
                    </label>
                    <x-text-input
                        wire:model.defer="to_wallet_evm"
                        id="to_wallet_evm"
                        type="text"
                        maxlength="64"
                        class="w-full"
                    />
                    @error('to_wallet_evm') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif

            @if (in_array('solana', $acceptedChains))
                <div>
                    <label for="wallet_solana" class="block mb-2 font-semibold text-gray-300">
                        {{ translate('Solana wallet address') }}
                    </label>
                    <x-text-input
                        wire:model.defer="wallets.solana"
                        id="wallet_solana"
                        type="text"
                        placeholder="Solana wallet address"
                        class="w-full"
                    />
                    @error('wallets.solana') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif

            @if (in_array('bitcoin', $acceptedChains))
                <div>
                    <label for="wallet_bitcoin" class="block mb-2 font-semibold text-gray-300">
                        {{ translate('Bitcoin wallet address') }}
                    </label>
                    <x-text-input
                        wire:model.defer="wallets.bitcoin"
                        id="wallet_bitcoin"
                        type="text"
                        placeholder="Bitcoin wallet address"
                        class="w-full"
                    />
                    @error('wallets.bitcoin') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif

            @if (in_array('ripple', $acceptedChains))
                <div>
                    <label for="wallet_xrp" class="block mb-2 font-semibold text-gray-300">
                        {{ translate('XRP wallet address') }}
                    </label>
                    <x-text-input
                        wire:model.defer="wallets.ripple"
                        id="wallet_xrp"
                        type="text"
                        placeholder="XRP wallet address"
                        class="w-full"
                    />
                    @error('wallets.ripple') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif

            @if (in_array('cardano', $acceptedChains))
                <div>
                    <label for="wallet_cardano" class="block mb-2 font-semibold text-gray-300">
                        {{ translate('Cardano wallet address') }}
                    </label>
                    <x-text-input
                        wire:model.defer="wallets.cardano"
                        id="wallet_cardano"
                        type="text"
                        placeholder="Cardano wallet address"
                        class="w-full"
                    />
                    @error('wallets.cardano') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif

            @if (in_array('algorand', $acceptedChains))
                <div>
                    <label for="wallet_algorand" class="block mb-2 font-semibold text-gray-300">
                        {{ translate('Algorand wallet address') }}
                    </label>
                    <x-text-input
                        wire:model.defer="wallets.algorand"
                        id="wallet_algorand"
                        type="text"
                        placeholder="Algorand wallet address"
                        class="w-full"
                    />
                    @error('wallets.algorand') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif

            @if (in_array('stellar', $acceptedChains))
                <div>
                    <label for="wallet_stellar" class="block mb-2 font-semibold text-gray-300">
                        {{ translate('Stellar wallet address') }}
                    </label>
                    <x-text-input
                        wire:model.defer="wallets.stellar"
                        id="wallet_stellar"
                        type="text"
                        placeholder="Stellar wallet address"
                        class="w-full"
                    />
                    @error('wallets.stellar') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif

            @if (in_array('tezos', $acceptedChains))
                <div>
                    <label for="wallet_tezos" class="block mb-2 font-semibold text-gray-300">
                        {{ translate('Tezos wallet address') }}
                    </label>
                    <x-text-input
                        wire:model.defer="wallets.tezos"
                        id="wallet_tezos"
                        type="text"
                        placeholder="Tezos wallet address"
                        class="w-full"
                    />
                    @error('wallets.tezos') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif

            <div>
                <label for="description" class="block mb-2 font-semibold text-gray-300">{{ translate('Description') }}</label>
                <x-textarea wire:model.defer="description" id="description" rows="3" class="w-full" />
                @error('description') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>

            <button
                type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded shadow transition relative disabled:opacity-50 disabled:cursor-not-allowed min-h-[2.5rem]"
                wire:loading.attr="disabled"
                wire:loading.class="pointer-events-none"
            >
                <span wire:loading.remove wire:target="submit" class="inline-flex items-center justify-center w-full h-full absolute inset-0">
                    {{ translate('Create Payment Request') }}
                </span>

                <span wire:loading wire:target="submit" class="inline-flex items-center justify-center w-full h-full absolute inset-0">
                    <svg class="animate-spin h-5 mt-2 w-5 text-white block mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                </span>
            </button>
        </form>
    </div>

    <div class="bg-darker text-white py-12 px-4 sm:px-6">
        <div class="w-full lg:max-w-screen-lg mx-auto text-gray-100 shadow-lg my-20">
            <h2 class="text-3xl lg:text-6xl font-bold mb-6">
                {!! translate('About Anonymous Crypto Payments') !!}
            </h2>
            <p class="text-xl text-gray-300 mb-6">
                {!! translate('Our platform enables fast, secure, and anonymous cryptocurrency payments with a low transaction fee of just 0.4%. Select your preferred fiat currency, specify the amount, and choose from a variety of supported blockchains and cryptocurrencies to receive payments directly to your wallet.') !!}
            </p>
            <p class="text-xl text-gray-300 mb-6">
                {!! translate('With no intermediaries, your funds move seamlessly from crypto to crypto. The process is designed for simplicity, ensuring you can create payment requests in minutes while maintaining full control over your transactions.') !!}
            </p>
            <h3 class="text-xl lg:text-3xl font-semibold text-gray-200 mb-4 mt-10">
                EVM {{ translate('Cryptowallets and Blockchains') }}
            </h3>
            <p class="text-xl text-gray-300 mb-4">
                {{ translate('For Ethereum Virtual Machine (EVM)-compatible chains, you only need to provide a single wallet address. This address will be used for all EVM-compatible networks, ensuring a streamlined experience.') }}
            </p>
            <p class="text-xl text-gray-300">
                {{ translate('Currently supported EVM chain:') }}
                <span class="font-medium">{{ implode(', ', $evmChains) }}.</span>
                {{ translate('more EVM-compatible chains, such as') }} Ethereum, Polygon, Arbitrum, Optimism, BNB Chain, Aalanche, Fantom, {{ translate('and') }} Linea, {{ translate('will be added soon') }}.
            </p>
        </div>
    </div>
</div>
