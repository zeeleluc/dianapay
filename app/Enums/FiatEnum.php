<?php

namespace App\Enums;

enum FiatEnum: string
{
    public static function all(): array
    {
        return array_keys(config('fiats', []));
    }

    public static function isValid(string $fiat): bool
    {
        return in_array(strtolower($fiat), self::all());
    }

    public static function decimalsFor(string $fiat): int
    {
        return match (strtolower($fiat)) {
            'jpy' => 0,
            default => 2,
        };
    }
}
