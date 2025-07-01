<?php

namespace App\Livewire;

use App\Models\AnonymousPaymentRequest;
use App\Services\CurrencyRateService;
use Livewire\Component;
use App\Models\CurrencyRate;

class AnonymousPayment extends Component
{
    public ?AnonymousPaymentRequest $anonymousPaymentRequest;
    public int $step = 1;
    public string $selectedChain = '';
    public string $selectedCrypto = '';
    public ?float $cryptoAmount = null;

    public function mount(string $uuid)
    {
        $this->anonymousPaymentRequest = AnonymousPaymentRequest::where('identifier', $uuid)->first();

        if (!$this->anonymousPaymentRequest) {
            abort(404, translate('Payment request not found.'));
        }

        $this->step = 1; // Always start at step 1
    }

    public function nextStep()
    {
        if ($this->step < 3) {
            $this->step++;
        }
    }

    public function previousStep()
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function getChainsProperty(): array
    {
        return \App\Enums\CryptoEnum::allChains();
    }

    public function getAvailableCryptosProperty(): array
    {
        return $this->selectedChain
            ? \App\Enums\CryptoEnum::forChain($this->selectedChain)
            : [];
    }


    public function updatedSelectedCrypto()
    {
        $this->updateCryptoAmount();
    }

    public function updatedSelectedChain()
    {
        $this->cryptoAmount = null;
    }

    public function updateCryptoAmount()
    {
        if (!$this->selectedChain || !$this->selectedCrypto || !$this->anonymousPaymentRequest) {
            $this->cryptoAmount = null;
            return;
        }

        $rate = CurrencyRateService::getRate(
            $this->anonymousPaymentRequest->fiat,
            $this->selectedCrypto,
            $this->selectedChain
        );

        if ($rate) {
            $fiatAmount = $this->anonymousPaymentRequest->amount_minor / (10 ** \App\Enums\FiatEnum::decimalsFor($this->anonymousPaymentRequest->fiat));
            $this->cryptoAmount = round($fiatAmount / $rate->rate, 8);
        } else {
            $this->cryptoAmount = null;
        }
    }

    public function render()
    {
        return view('livewire.anonymous-payment', [
            'fiats' => array_keys(config('fiats')),
        ])->layout('components.layouts.clean');
    }
}
