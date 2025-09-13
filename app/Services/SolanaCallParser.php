<?php

namespace App\Services;

use App\Helpers\SlackNotifier;
use App\Models\SolanaCall;

class SolanaCallParser
{
    private const SOLANA_CALL_PHRASE = '🔥 First Call 🚀 SOLANA100XCALL • Premium Signals @';

    /**
     * Parse the message into simplified data and save to DB.
     *
     * @param string $message The raw message string
     * @return SolanaCall|null
     */
    public static function parseAndSave(string $message): ?SolanaCall
    {
        $lines = array_map('trim', preg_split('/\r?\n/', $message));
        $data = [];

        // ---- Token header ----
        $firstLine = array_shift($lines); // grab first line
        if ($firstLine) {
            // Remove leading emojis or symbols
            $firstLine = preg_replace('/^[^\p{L}\p{N}]+/u', '', $firstLine);

            // Extract anything in parentheses as ticker
            if (preg_match('/\((.*?)\)/', $firstLine, $matches)) {
                $data['token_ticker'] = trim($matches[1]);
                // Remove parentheses content from name
                $firstLine = preg_replace('/\s*\(.*?\)\s*/', '', $firstLine);
            } else {
                $data['token_ticker'] = ''; // leave empty if none
            }

            $data['token_name'] = trim($firstLine);
        }

        // ---- Token address and age ----
        $addressLine = $lines[0] ?? null;
        $ageLine = $lines[1] ?? null;

        if ($addressLine) {
            // Remove leading ├ or └ and any whitespace
            $addressLine = ltrim($addressLine, "├└ \t\n\r\0\x0B");
            $data['token_address'] = $addressLine;
        }

        if ($ageLine && preg_match('/🌱(\d+)([smh])/u', $ageLine, $matches)) {
            $age = (int)$matches[1];
            $unit = strtolower($matches[2]);
            $data['age_minutes'] = match($unit) {
                's' => ceil($age / 60),
                'm' => $age,
                'h' => $age * 60,
                default => null
            };
        }


        // ---- Stats section ----
        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^├\s*USD\s*\$(.+)/', $line, $m)) {
                $data['usd_price'] = self::parseNumber($m[1]);
            } elseif (preg_match('/^├\s*MC\s*\$(.+)/', $line, $m)) {
                $data['market_cap'] = self::parseNumber($m[1]);
            } elseif (preg_match('/^├\s*Vol\s*\$(.+)/', $line, $m)) {
                $data['volume_24h'] = self::parseNumber($m[1]);
            } elseif (preg_match('/^├\s*LP\s*\$(.+)/', $line, $m)) {
                $data['liquidity_pool'] = self::parseNumber($m[1]);
            } elseif (preg_match('/^└\s*ATH\s*\$(.+)/', $line, $m)) {
                $data['all_time_high'] = self::parseNumber($m[1]);
            } elseif (preg_match('/├\s*Top 10\s*(\d+)%/', $line, $m)) {
                $data['top_10_holders_percent'] = (float) $m[1];
            } elseif (preg_match('/├\s*Dev Sold\s*(🟢|🔴)/', $line, $m)) {
                $data['dev_sold'] = $m[1] === '🟢';
            } elseif (preg_match('/└\s*DEX Paid\s*(🟢|🔴)/', $line, $m)) {
                $data['dev_paid_status'] = $m[1] === '🟢';
            }
        }

        // ---- Save if token address exists ----
        if (!empty($data['token_address'])) {
            return SolanaCall::createFromParsed($data);
        }

        return null;
    }

    /**
     * Normalize subscript digits and parse numbers like "0.0₄1293" or "12.3K" to float.
     */
    /**
     * Normalize subscript digits and parse numbers like "0.0₄1293" or "12.3K" to float.
     */
    private static function parseNumber(string $str): ?float
    {
        $str = trim($str);

        // Convert subscript digits ₀-₉
        $subMap = ['₀'=>0,'₁'=>1,'₂'=>2,'₃'=>3,'₄'=>4,'₅'=>5,'₆'=>6,'₇'=>7,'₈'=>8,'₉'=>9];
        $str = strtr($str, $subMap);

        // Remove leading symbols, keep digits, dots, commas, minus, and K/M/B
        $str = preg_replace('/[^\d\.\,\-KMBkmb]/', '', $str);

        if ($str === '') return null;

        // Replace comma with dot if present (e.g., "1,23" => "1.23")
        $str = str_replace(',', '.', $str);

        // Detect multiplier
        $multiplier = 1;
        if (str_ends_with($str, 'K') || str_ends_with($str, 'k')) {
            $multiplier = 1_000;
            $str = substr($str, 0, -1);
        } elseif (str_ends_with($str, 'M') || str_ends_with($str, 'm')) {
            $multiplier = 1_000_000;
            $str = substr($str, 0, -1);
        } elseif (str_ends_with($str, 'B') || str_ends_with($str, 'b')) {
            $multiplier = 1_000_000_000;
            $str = substr($str, 0, -1);
        }

        return (float) $str * $multiplier;
    }
}
