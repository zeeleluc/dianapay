<div class="max-w-md mx-auto bg-gray-900 p-6 rounded-lg text-gray-100 shadow-lg mt-8">
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

            <x-dropdown align="left" width="48" :contentClasses="'py-2 bg-gray-800 text-white max-h-60 overflow-auto'">
                <x-slot name="trigger">
                    <button type="button" class="w-full text-left px-3 py-2 rounded bg-gray-800 border border-gray-700 text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                <!-- Main amount input -->
                <x-text-input
                    wire:model.defer="amount"
                    id="amount"
                    type="number"
                    min="0"
                    step="{{ $this->hasMinorAmount ? '1' : 'any' }}"
                    class="flex-grow"
                />

                <!-- Minor amount input (like cents) -->
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
                {{ translate('Select the cryptocurrencies and networks you accept') }}
            </label>
            @foreach (\App\Enums\CryptoEnum::grouped() as $chain => $cryptos)
                <div class="mb-4 border border-gray-700 p-3 rounded">
                    {{-- Parent Checkbox --}}
                    <label
                        class="cursor-pointer inline-flex items-center font-bold text-lg tracking-wide text-gray-300
                           bg-gray-800 border-none rounded
                           transition-all duration-200 hover:bg-gray-700
                           [&>input:checked+span]:bg-blue-700
                           [&>input:checked+span]:text-white
                           [&>input:checked+span]:border-blue-700"
                        >
                        <input
                            type="checkbox"
                            wire:model="acceptedChains"
                            value="{{ $chain }}"
                            wire:change="syncCryptoSelection('{{ $chain }}')"
                            class="hidden"
                        >
                        <span class="rounded px-3 py-1 border border-gray-600 uppercase">{{ $chain }}</span>
                    </label>

                    @if (in_array($chain, $acceptedChains))
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($cryptos as $crypto)
                                <label
                                    class="cursor-pointer bg-gray-800 text-gray-300 border-none rounded px-0 py-0 text-sm transition-all duration-200 hover:bg-gray-700
                                   flex items-center
                                   [&>input:checked+span]:bg-blue-600
                                   [&>input:checked+span]:text-white
                                   [&>input:checked+span]:border-blue-600"
                                >
                                    <input
                                        type="checkbox"
                                        wire:model="acceptedCryptoSelection"
                                        value="{{ $crypto['symbol'] }}"
                                        class="hidden"
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
                    {{ translate('To EVM Cryptowallet') }}
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

        {{-- Solana Wallet --}}
        @if (in_array('solana', $acceptedChains))
            <div>
                <label for="to_wallet_solana" class="block mb-2 font-semibold text-gray-300">
                    {{ translate('To Solana Cryptowallet') }}
                </label>
                <x-text-input
                    wire:model.defer="to_wallet_solana"
                    id="to_wallet_solana"
                    type="text"
                    maxlength="64"
                    class="w-full"
                />
                @error('to_wallet_solana') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>
        @endif

        {{-- Bitcoin Wallet --}}
        @if (in_array('bitcoin', $acceptedChains))
            <div>
                <label for="to_wallet_bitcoin" class="block mb-2 font-semibold text-gray-300">
                    {{ translate('To Bitcoin Wallet') }}
                </label>
                <x-text-input
                    wire:model.defer="to_wallet_bitcoin"
                    id="to_wallet_bitcoin"
                    type="text"
                    maxlength="64"
                    class="w-full"
                />
                @error('to_wallet_bitcoin') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>
        @endif

        {{-- XRP Wallet --}}
        @if (in_array('xrp', $acceptedChains))
            <div>
                <label for="to_wallet_xrp" class="block mb-2 font-semibold text-gray-300">
                    {{ translate('To XRP Wallet') }}
                </label>
                <x-text-input
                    wire:model.defer="to_wallet_xrp"
                    id="to_wallet_xrp"
                    type="text"
                    maxlength="64"
                    class="w-full"
                />
                @error('to_wallet_xrp') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>
        @endif

        {{-- Repeat similarly for cardano, algorand, stellar, tezos --}}
        @if (in_array('cardano', $acceptedChains))
            <div>
                <label for="to_wallet_cardano" class="block mb-2 font-semibold text-gray-300">
                    {{ translate('To Cardano Wallet') }}
                </label>
                <x-text-input
                    wire:model.defer="to_wallet_cardano"
                    id="to_wallet_cardano"
                    type="text"
                    maxlength="64"
                    class="w-full"
                />
                @error('to_wallet_cardano') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>
        @endif

        @if (in_array('algorand', $acceptedChains))
            <div>
                <label for="to_wallet_algorand" class="block mb-2 font-semibold text-gray-300">
                    {{ translate('To Algorand Wallet') }}
                </label>
                <x-text-input
                    wire:model.defer="to_wallet_algorand"
                    id="to_wallet_algorand"
                    type="text"
                    maxlength="64"
                    class="w-full"
                />
                @error('to_wallet_algorand') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>
        @endif

        @if (in_array('stellar', $acceptedChains))
            <div>
                <label for="to_wallet_stellar" class="block mb-2 font-semibold text-gray-300">
                    {{ translate('To Stellar Wallet') }}
                </label>
                <x-text-input
                    wire:model.defer="to_wallet_stellar"
                    id="to_wallet_stellar"
                    type="text"
                    maxlength="64"
                    class="w-full"
                />
                @error('to_wallet_stellar') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>
        @endif

        @if (in_array('tezos', $acceptedChains))
            <div>
                <label for="to_wallet_tezos" class="block mb-2 font-semibold text-gray-300">
                    {{ translate('To Tezos Wallet') }}
                </label>
                <x-text-input
                    wire:model.defer="to_wallet_tezos"
                    id="to_wallet_tezos"
                    type="text"
                    maxlength="64"
                    class="w-full"
                />
                @error('to_wallet_tezos') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>
        @endif

        <div>
            <label for="description" class="block mb-2 font-semibold text-gray-300">{{ translate('Description') }}</label>
            <x-textarea
                wire:model.defer="description"
                id="description"
                rows="3"
                class="w-full"
            />
            @error('description') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded shadow transition">
            {{ translate('Create Payment Request') }}
        </button>
    </form>
</div>
