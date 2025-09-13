<?php

namespace App\Services;

use App\Models\SolanaCall;

class SolanaCallParser
{
    /**
     * Parse the message into simplified data and save to DB.
     */
    public static function parseAndSave(string $message): ?SolanaCall
    {
        $lines = array_map('trim', preg_split('/\r?\n/', $message));
        $data = [];

        // ---- Token header ----
        $firstLine = array_shift($lines);
        if ($firstLine) {
            // Remove leading symbols/emojis
            $firstLine = preg_replace('/^[^\p{L}\p{N}]+/u', '', $firstLine);

            // Extract ticker in parentheses
            if (preg_match('/\((.*?)\)/', $firstLine, $matches)) {
                $data['token_ticker'] = trim($matches[1]);
                $firstLine = preg_replace('/\s*\(.*?\)\s*/', '', $firstLine);
            } else {
                $data['token_ticker'] = '';
            }

            $data['token_name'] = trim($firstLine);
        }

        // ---- Address and age ----
        $addressLine = $lines[0] ?? null;
        $ageLine = $lines[1] ?? null;

        if ($addressLine) {
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

        // ---- Stats ----
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
            } elseif (preg_match('/^├\s*Sup\s*(.+)/', $line, $m)) {
                $data['supply'] = self::parseNumber($m[1]);
            } elseif (preg_match('/^├\s*1H\s*(.+)/', $line, $m)) {
                $data['one_hour_change'] = self::parseNumber($m[1]);
            } elseif (preg_match('/^└\s*ATH\s*\$(.+)/', $line, $m)) {
                $data['all_time_high'] = self::parseNumber($m[1]);
            } elseif (preg_match('/├\s*Top 10\s*(\d+)%/', $line, $m)) {
                $data['top_10_holders_percent'] = (float)$m[1];
            } elseif (preg_match('/├\s*Dev Sold\s*(🟢|🔴)/', $line, $m)) {
                $data['dev_sold'] = $m[1] === '🟢';
            } elseif (preg_match('/└\s*DEX Paid\s*(🟢|🔴)/', $line, $m)) {
                $data['dex_paid_status'] = $m[1] === '🟢';
            }
        }

        // ---- Save if token address exists ----
        if (!empty($data['token_address'])) {
            return SolanaCall::createFromParsed($data);
        }

        return null;
    }

    /**
     * Convert formatted strings like "123.5K", "1.64M", "0.0002135" to floats.
     */
    private static function parseNumber(string $str): ?float
    {
        $str = trim($str);

        // Map subscript digits to normal digits
        $subMap = ['₀'=>0,'₁'=>1,'₂'=>2,'₃'=>3,'₄'=>4,'₅'=>5,'₆'=>6,'₇'=>7,'₈'=>8,'₉'=>9];
        $str = strtr($str, $subMap);

        // Remove emojis or non-numeric symbols except K/M/B
        $str = preg_replace('/[^\d\.\-KMBkmb]/u', '', $str);
        if ($str === '') return null;

        $multiplier = 1;
        $lastChar = strtoupper(substr($str, -1));

        if ($lastChar === 'K') $multiplier = 1_000;
        elseif ($lastChar === 'M') $multiplier = 1_000_000;
        elseif ($lastChar === 'B') $multiplier = 1_000_000_000;

        if (in_array($lastChar, ['K', 'M', 'B'])) {
            $str = substr($str, 0, -1);
        }

        return (float)$str * $multiplier;
    }
}
