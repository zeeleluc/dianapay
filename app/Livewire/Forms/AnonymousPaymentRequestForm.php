<?php

namespace App\Livewire\Forms;

use App\Enums\FiatEnum;
use App\Models\AnonymousPaymentRequest;
use App\Rules\WalletAddress;
use Livewire\Component;

class AnonymousPaymentRequestForm extends Component
{
    public $fiat = '';
    public $amount;
    public $amount_minor;

    public $to_wallet_evm;
    public $to_wallet_solana;
    public $to_wallet_bitcoin;
    public $to_wallet_xrp;
    public $to_wallet_cardano;
    public $to_wallet_algorand;
    public $to_wallet_stellar;
    public $to_wallet_tezos;

    public $description;

    public array $acceptedCryptoSelection = [];
    public array $acceptedChains = [];

    public function mount()
    {
        // Initialize acceptedChains from config
        $allChains = array_keys(config('cryptocurrencies'));
        $this->acceptedChains = $allChains;

        // Get all crypto symbols for all chains
        $allCryptos = [];
        foreach ($allChains as $chain) {
            $cryptos = \App\Enums\CryptoEnum::forChain($chain);
            foreach ($cryptos as $crypto) {
                $allCryptos[] = $crypto['symbol'];
            }
        }
        $this->acceptedCryptoSelection = $allCryptos;

        // Reset form to initialize all properties including fiat prefill
        $this->resetForm();
    }

    private function resetForm()
    {
        // Reset all fields except acceptedChains and acceptedCryptoSelection because
        // they are initialized in mount and should not be reset to empty here.
        $this->reset([
            'fiat',
            'amount',
            'amount_minor',
            'to_wallet_evm',
            'to_wallet_solana',
            'to_wallet_bitcoin',
            'to_wallet_xrp',
            'to_wallet_cardano',
            'to_wallet_algorand',
            'to_wallet_stellar',
            'to_wallet_tezos',
            'description',
        ]);

        // Prefill fiat based on locale session
        $locale = session('locale', 'en');

        $localeToFiat = [
            'en' => 'usd',
            'es' => 'usd',
            'fr' => 'eur',
            'de' => 'eur',
            'ru' => 'usd',
            'ja' => 'jpy',
            'zh-CN' => 'cny',
            'zh-TW' => 'cny',
            'pt' => 'usd',
            'it' => 'eur',
            'ko' => 'usd',
            'ar' => 'usd',
            'hi' => 'usd',
            'tr' => 'usd',
            'nl' => 'eur',
            'sv' => 'eur',
            'pl' => 'eur',
            'vi' => 'usd',
            'id' => 'usd',
            'th' => 'usd',
            'ms' => 'usd',
            'fa' => 'usd',
        ];

        $allowedFiats = ['usd', 'eur', 'jpy', 'gbp', 'cny', 'cad', 'aud', 'chf', 'xcg'];

        $fiatGuess = $localeToFiat[$locale] ?? 'usd';

        $this->fiat = in_array($fiatGuess, $allowedFiats) ? $fiatGuess : 'usd';
    }

    protected function rules()
    {
        $decimals = FiatEnum::decimalsFor($this->fiat);
        $max_minor = (int) str_repeat('9', $decimals);

        $rules = [
            'fiat' => ['required', 'string', function ($attribute, $value, $fail) {
                if (!FiatEnum::isValid(strtolower($value))) {
                    $fail(translate("The selected {$attribute} is invalid."));
                }
            }],
            'amount' => 'required|numeric|min:0',
            'amount_minor' => ['nullable', 'integer', 'min:0', "max:{$max_minor}"],
            'description' => 'required|string',
            'acceptedCryptoSelection' => ['required', 'array', 'min:1'],
        ];

        $evmChains = ['base', 'polygon', 'bnb', 'ethereum'];

        if (count(array_intersect($evmChains, $this->acceptedChains)) > 0) {
            $rules['to_wallet_evm'] = ['required', 'string', 'max:64', new WalletAddress('evm')];
        }
        if (in_array('solana', $this->acceptedChains)) {
            $rules['to_wallet_solana'] = ['required', 'string', 'max:64', new WalletAddress('solana')];
        }
        if (in_array('bitcoin', $this->acceptedChains)) {
            $rules['to_wallet_bitcoin'] = ['required', 'string', 'max:64', new WalletAddress('bitcoin')];
        }
        if (in_array('xrp', $this->acceptedChains)) {
            $rules['to_wallet_xrp'] = ['required', 'string', 'max:64', new WalletAddress('xrp')];
        }
        if (in_array('cardano', $this->acceptedChains)) {
            $rules['to_wallet_cardano'] = ['required', 'string', 'max:64', new WalletAddress('cardano')];
        }
        if (in_array('algorand', $this->acceptedChains)) {
            $rules['to_wallet_algorand'] = ['required', 'string', 'max:64', new WalletAddress('algorand')];
        }
        if (in_array('stellar', $this->acceptedChains)) {
            $rules['to_wallet_stellar'] = ['required', 'string', 'max:64', new WalletAddress('stellar')];
        }
        if (in_array('tezos', $this->acceptedChains)) {
            $rules['to_wallet_tezos'] = ['required', 'string', 'max:64', new WalletAddress('tezos')];
        }

        return $rules;
    }

    protected function messages()
    {
        return [
            'fiat.required' => translate('The fiat currency is required.'),
            'fiat.string' => translate('The fiat currency must be a string.'),

            'amount.required' => translate('The amount is required.'),
            'amount.numeric' => translate('The amount must be a number.'),
            'amount.min' => translate('The amount must be at least 0.'),

            'amount_minor.integer' => translate('The minor amount must be an integer.'),
            'amount_minor.min' => translate('The minor amount must be at least 0.'),
            'amount_minor.max' => translate('The minor amount is invalid for the selected fiat currency.'),

            'to_wallet_evm.required' => translate('The destination EVM wallet is required.'),
            'to_wallet_evm.string' => translate('The destination EVM wallet must be a string.'),
            'to_wallet_evm.max' => translate('The destination EVM wallet may not be greater than 64 characters.'),

            'to_wallet_solana.required' => translate('The destination Solana wallet is required.'),
            'to_wallet_solana.string' => translate('The destination Solana wallet must be a string.'),
            'to_wallet_solana.max' => translate('The destination Solana wallet may not be greater than 64 characters.'),

            'to_wallet_bitcoin.required' => translate('The destination Bitcoin wallet is required.'),
            'to_wallet_bitcoin.string' => translate('The destination Bitcoin wallet must be a string.'),
            'to_wallet_bitcoin.max' => translate('The destination Bitcoin wallet may not be greater than 64 characters.'),

            'to_wallet_xrp.required' => translate('The destination XRP wallet is required.'),
            'to_wallet_xrp.string' => translate('The destination XRP wallet must be a string.'),
            'to_wallet_xrp.max' => translate('The destination XRP wallet may not be greater than 64 characters.'),

            'to_wallet_cardano.required' => translate('The destination Cardano wallet is required.'),
            'to_wallet_cardano.string' => translate('The destination Cardano wallet must be a string.'),
            'to_wallet_cardano.max' => translate('The destination Cardano wallet may not be greater than 64 characters.'),

            'to_wallet_algorand.required' => translate('The destination Algorand wallet is required.'),
            'to_wallet_algorand.string' => translate('The destination Algorand wallet must be a string.'),
            'to_wallet_algorand.max' => translate('The destination Algorand wallet may not be greater than 64 characters.'),

            'to_wallet_stellar.required' => translate('The destination Stellar wallet is required.'),
            'to_wallet_stellar.string' => translate('The destination Stellar wallet must be a string.'),
            'to_wallet_stellar.max' => translate('The destination Stellar wallet may not be greater than 64 characters.'),

            'to_wallet_tezos.required' => translate('The destination Tezos wallet is required.'),
            'to_wallet_tezos.string' => translate('The destination Tezos wallet must be a string.'),
            'to_wallet_tezos.max' => translate('The destination Tezos wallet may not be greater than 64 characters.'),

            'description.required' => translate('The description is required.'),
            'description.string' => translate('The description must be a string.'),

            'acceptedCryptoSelection.required' => translate('You must select at least one cryptocurrency.'),
            'acceptedCryptoSelection.array' => translate('The cryptocurrency selection must be an array.'),
            'acceptedCryptoSelection.min' => translate('You must select at least one cryptocurrency.'),
        ];
    }

    public function submit()
    {
        $this->validate();

        $decimals = FiatEnum::decimalsFor($this->fiat);
        $amount_minor = (int) ($this->amount_minor ?? 0);
        $max_minor = (int) str_repeat('9', $decimals);
        $amount_minor = max(0, min($amount_minor, $max_minor));

        $amount_minor_total = (int) round($this->amount * (10 ** $decimals)) + $amount_minor;

        $groupedCrypto = [];
        foreach ($this->acceptedCryptoSelection as $cryptoSymbol) {
            $chain = \App\Enums\CryptoEnum::chain($cryptoSymbol);
            if ($chain) {
                $groupedCrypto[$chain][] = $cryptoSymbol;
            }
        }

        AnonymousPaymentRequest::create([
            'fiat' => strtolower($this->fiat),
            'amount_minor' => $amount_minor_total,
            'to_wallet_evm' => $this->to_wallet_evm,
            'to_wallet_solana' => $this->to_wallet_solana,
            'to_wallet_bitcoin' => $this->to_wallet_bitcoin,
            'to_wallet_xrp' => $this->to_wallet_xrp,
            'to_wallet_cardano' => $this->to_wallet_cardano,
            'to_wallet_algorand' => $this->to_wallet_algorand,
            'to_wallet_stellar' => $this->to_wallet_stellar,
            'to_wallet_tezos' => $this->to_wallet_tezos,
            'accepted_crypto' => json_encode($groupedCrypto),
            'description' => $this->description,
        ]);

        session()->flash('message', translate('Payment request created successfully!'));
        $this->resetForm();
    }

    public function getHasMinorAmountProperty(): bool
    {
        return FiatEnum::decimalsFor($this->fiat) > 0;
    }

    public function syncCryptoSelection(string $chain)
    {
        $cryptos = \App\Enums\CryptoEnum::forChain($chain);

        if (in_array($chain, $this->acceptedChains)) {
            foreach ($cryptos as $crypto) {
                if (!in_array($crypto['symbol'], $this->acceptedCryptoSelection)) {
                    $this->acceptedCryptoSelection[] = $crypto['symbol'];
                }
            }
        } else {
            $this->acceptedCryptoSelection = array_filter(
                $this->acceptedCryptoSelection,
                fn ($symbol) => !in_array($symbol, array_column($cryptos, 'symbol'))
            );
        }
    }

    public function render()
    {
        return view('livewire.forms.anonymous-payment-request-form', [
            'fiats' => config('fiats'),
        ])->layout('layouts.homepage');
    }
}
