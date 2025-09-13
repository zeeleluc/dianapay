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
        $headerText = implode("\n", array_slice($lines, 0, 5));

        // Match token name (ignore emoji) and address; age is optional
        if (preg_match(
            '/(?:💊\s*)?(.+?)\s*\(\$[A-Za-z0-9]+\)?\s*\n├\s*([A-Za-z0-9]+)\s*(?:\n└.*🌱(\d+)([smh]))?/u',
            $headerText,
            $matches
        )) {
            $data['token_name'] = trim($matches[1]);
            $data['token_address'] = $matches[2];

            if (!empty($matches[3]) && !empty($matches[4])) {
                $age = (int) $matches[3];
                $unit = strtolower($matches[4]);
                $data['age_minutes'] = match($unit) {
                    's' => ceil($age / 60),
                    'm' => $age,
                    'h' => $age * 60,
                    default => null
                };
            }
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
    private static function parseNumber(string $str): ?float
    {
        $str = trim($str);

        // Convert subscript digits ₀-₉
        $subMap = ['₀'=>0,'₁'=>1,'₂'=>2,'₃'=>3,'₄'=>4,'₅'=>5,'₆'=>6,'₇'=>7,'₈'=>8,'₉'=>9];
        $str = strtr($str, $subMap);

        // Remove non-digit, non-dot, non-minus characters (keep decimal)
        $str = preg_replace('/[^\d\.\-]/', '', $str);

        if ($str === '') return null;

        return (float) $str;
    }
}
