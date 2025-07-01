<?php

namespace App\Enums;

class CryptoEnum
{
    /**
     * Get all blockchain keys (base, solana, etc.)
     */
    public static function allChains(): array
    {
        return array_keys(config('cryptocurrencies', []));
    }

    /**
     * Get all cryptos (flat list of symbols across all chains)
     */
    public static function all(): array
    {
        return collect(config('cryptocurrencies'))
            ->flatMap(fn ($tokens) => array_keys($tokens))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get cryptos for a specific chain (e.g., 'base')
     */
    public static function forChain(string $chain): array
    {
        return collect(config("cryptocurrencies.{$chain}", []))
            ->map(fn ($data, $symbol) => [
                'symbol' => $symbol,
                'coingecko_id' => $data['coingecko_id'],
                'contract' => $data['contract'] ?? null,
                'chain' => $chain,
            ])
            ->values()
            ->all();
    }

    /**
     * Get grouped options: [ 'base' => [...], 'solana' => [...], ... ]
     */
    public static function grouped(): array
    {
        return collect(config('cryptocurrencies'))
            ->map(fn ($tokens, $chain) => self::forChain($chain))
            ->toArray();
    }

    /**
     * Check if a symbol exists under any chain
     */
    public static function isValid(string $symbol): bool
    {
        return in_array(strtoupper($symbol), self::all(), true);
    }

    /**
     * Find info by symbol (searches all chains)
     */
    public static function get(string $symbol): ?array
    {
        foreach (self::allChains() as $chain) {
            if (isset(config("cryptocurrencies.{$chain}")[strtoupper($symbol)])) {
                $data = config("cryptocurrencies.{$chain}")[strtoupper($symbol)];
                return [
                    'symbol' => strtoupper($symbol),
                    'coingecko_id' => $data['coingecko_id'],
                    'contract' => $data['contract'] ?? null,
                    'chain' => $chain,
                ];
            }
        }

        return null;
    }

    public static function coingeckoId(string $symbol): ?string
    {
        return self::get($symbol)['coingecko_id'] ?? null;
    }

    public static function contract(string $symbol): ?string
    {
        return self::get($symbol)['contract'] ?? null;
    }

    public static function chain(string $symbol): ?string
    {
        return self::get($symbol)['chain'] ?? null;
    }
}
