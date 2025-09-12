<?php

namespace App\Services;

use App\Models\SolanaCall;

class SolanaCallParser
{
    private const SOLANA_CALL_PHRASE = '🔥 First Call 🚀 SOLANA100XCALL • Premium Signals @';

    /**
     * Parse the message into simplified data and save to DB.
     *
     * @param string $message The raw message string
     * @return SolanaCall|null The saved model or null if not a valid Solana call
     */
    public static function parseAndSave(string $message): ?SolanaCall
    {
        if (stripos($message, self::SOLANA_CALL_PHRASE) === false) {
            return null;  // Not a Solana call
        }

        $lines = explode("\n", trim($message));
        $data = [];

        // Token Name, Address, Age (first lines)
        $headerPattern = '/­💊\s*(.+?)\s*\n\s*├\s*([A-Za-z0-9]+)\s*\n\s*└\s*#SOL\s*\|\s*🌱(\d+)s?/';
        if (preg_match($headerPattern, implode("\n", array_slice($lines, 0, 3)), $matches)) {
            $data['token_name'] = trim($matches[1]);
            $data['token_address'] = $matches[2];
            $data['age_minutes'] = (int) $matches[3];  // e.g., 29 from "29s"
        }

        // Market Cap, Volume 24h, Liquidity Pool, All Time High (Stats section)
        foreach ($lines as $line) {
            if (preg_match('/├ MC\s*\$(.+)/', $line, $m)) {
                $data['market_cap'] = self::parseNumber($m[1]);
            } elseif (preg_match('/├ Vol\s*\$(.+)/', $line, $m)) {
                $data['volume_24h'] = self::parseNumber($m[1]);
            } elseif (preg_match('/├ LP\s*\$(.+)/', $line, $m)) {
                $data['liquidity_pool'] = self::parseNumber($m[1]);
            } elseif (preg_match('/└ ATH\s*\$(.+)/', $line, $m)) {
                $data['all_time_high'] = self::parseNumber($m[1]);
            }
        }

        // Top 10 Holders %
        foreach ($lines as $line) {
            if (preg_match('/├ Top 10\s*(\d+)%/', $line, $m)) {
                $data['top_10_holders_percent'] = (float) $m[1];
            }
        }

        // Dev Sold (🟢 = true, else false)
        foreach ($lines as $line) {
            if (preg_match('/├ Dev Sold\s*(🟢|🔴|\?|\w+)/', $line, $m)) {
                $data['dev_sold'] = trim($m[1]) === '🟢';
            }
        }

        // DEX Paid Status (🟢 = true, else false)
        foreach ($lines as $line) {
            if (preg_match('/└ DEX Paid\s*(🟢|🔴)/', $line, $m)) {
                $data['dev_paid_status'] = trim($m[1]) === '🟢';
            }
        }

        // Save to DB if token_address is present
        if (!empty($data['token_address'])) {
            return SolanaCall::createFromParsed($data);
        }

        return null;
    }

    /**
     * Parse numbers like "83.9K", "102.4K", "18.9K", "0.0₄8390".
     */
    private static function parseNumber(string $str): ?float
    {
        $originalStr = $str;  // For fallback

        // Handle K/M suffix (e.g., "83.9K" → 83900)
        if (preg_match('/^([\d.]+)([kKmM])$/i', trim($str), $suffixMatch)) {
            $num = (float) $suffixMatch[1];
            $suffix = strtoupper($suffixMatch[2]);
            if ($suffix === 'K') return $num * 1000;
            if ($suffix === 'M') return $num * 1000000;
        }

        // Handle subscript for USD (e.g., "0.0₄8390" → 0.00008390)
        if (preg_match('/0\.0([₀-₉])(\d+)/', $str, $subMatch)) {
            $subDigit = ['₀' => 0, '₁' => 1, '₂' => 2, '₃' => 3, '₄' => 4, '₅' => 5, '₆' => 6, '₇' => 7, '₈' => 8, '₉' => 9][$subMatch[1]] ?? 0;
            $remainingDigits = $subMatch[2];
            $decimalPart = str_pad((string) $subDigit . $remainingDigits, 6, '0', STR_PAD_RIGHT);
            $str = '0.' . $decimalPart;
        }

        // Clean and convert (remove $, %, (, ) etc.)
        $str = preg_replace('/[^\d.]/', '', $str);

        $num = (float) $str;
        return $num > 0 ? $num : null;  // Return null for 0 or invalid
    }
}
