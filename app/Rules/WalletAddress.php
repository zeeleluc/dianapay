<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class WalletAddress implements Rule
{
    protected string $chain;

    public function __construct(string $chain)
    {
        $this->chain = strtolower($chain);
    }

    public function passes($attribute, $value): bool
    {
        $patterns = [
            'evm' => '/^0x[a-fA-F0-9]{40}$/',
            'solana' => '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/',
            'bitcoin' => '/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,39}$/',
            'xrp' => '/^r[1-9A-HJ-NP-Za-km-z]{25,35}$/',
            'cardano' => '/^addr1[0-9a-z]{58}$/',
            'algorand' => '/^[A-Z2-7]{58}$/',
            'stellar' => '/^G[A-Z2-7]{55}$/',
            'tezos' => '/^(tz1|tz2|tz3)[0-9A-Za-z]{33}$/',
        ];

        if (!isset($patterns[$this->chain])) {
            return true; // geen patroon, accepteren
        }

        return preg_match($patterns[$this->chain], $value) === 1;
    }

    public function message()
    {
        return "The :attribute field is not a valid wallet address for {$this->chain}.";
    }
}
