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
        try {
            if (stripos($message, self::SOLANA_CALL_PHRASE) === false) {
                return null;
            }

            $lines = array_map('trim', preg_split('/\r?\n/', $message));
            $data = [];

            // ---- Token header ----
            $headerText = implode("\n", array_slice($lines, 0, 5));

            if (preg_match(
                '/^\s*.+?\s*(.+?)\s*\n├\s*([A-Za-z0-9]+)\s*(?:\n└.*🌱(\d+)([sm]))?/u',
                $headerText,
                $matches
            )) {
                $data['token_name'] = trim($matches[1]);
                $data['token_address'] = $matches[2];

                if (!empty($matches[3]) && !empty($matches[4])) {
                    $age = (int) $matches[3];
                    $unit = strtolower($matches[4]);
                    $data['age_minutes'] = $unit === 's' ? ceil($age / 60) : $age;
                }
            }

            // ---- Stats section ----
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^├\s*MC\s*\$(.+)/', $line, $m)) {
                    $data['market_cap'] = self::parseNumber($m[1]);
                } elseif (preg_match('/^├\s*Vol\s*\$(.+)/', $line, $m)) {
                    $data['volume_24h'] = self::parseNumber($m[1]);
                } elseif (preg_match('/^├\s*LP\s*\$(.+)/', $line, $m)) {
                    $data['liquidity_pool'] = self::parseNumber($m[1]);
                } elseif (preg_match('/^└\s*ATH\s*\$(.+)/', $line, $m)) {
                    $data['all_time_high'] = self::parseNumber($m[1]);
                }
            }

            // ---- Top 10 holders ----
            foreach ($lines as $line) {
                if (preg_match('/├\s*Top 10\s*(\d+)%/', $line, $m)) {
                    $data['top_10_holders_percent'] = (float) $m[1];
                }
            }

            // ---- Dev Sold ----
            foreach ($lines as $line) {
                if (preg_match('/├\s*Dev Sold\s*(🟢|🔴)/', $line, $m)) {
                    $data['dev_sold'] = $m[1] === '🟢';
                }
            }

            // ---- DEX Paid ----
            foreach ($lines as $line) {
                if (preg_match('/└\s*DEX Paid\s*(🟢|🔴)/', $line, $m)) {
                    $data['dev_paid_status'] = $m[1] === '🟢';
                }
            }

            // ---- Save if token address exists ----
            if (!empty($data['token_address'])) {
                $call = SolanaCall::createFromParsed($data);

                SlackNotifier::success("Parsed Solana call: {$call->token_name} ({$call->token_address})");
                return $call;
            }

            SlackNotifier::warning("Failed to extract token address from message:\n" . mb_strimwidth($message, 0, 300, '...'));
            return null;

        } catch (Throwable $e) {
            SlackNotifier::error("Parser exception: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Parse numbers with suffixes or subscript notation.
     * e.g., "83.9K", "102.4M", "0.0₄4084" → float
     */
    private static function parseNumber(string $str): ?float
    {
        $str = trim($str);

        // Handle K/M suffix
        if (preg_match('/^([\d,.]+)\s*([kKmM])$/', $str, $match)) {
            $num = (float) str_replace(',', '', $match[1]);
            $suffix = strtoupper($match[2]);
            return $suffix === 'K' ? $num * 1_000 : $num * 1_000_000;
        }

        // Handle subscript digits (₀-₉)
        if (preg_match_all('/[₀-₉]/u', $str, $subMatches)) {
            $subMap = ['₀'=>0,'₁'=>1,'₂'=>2,'₃'=>3,'₄'=>4,'₅'=>5,'₆'=>6,'₇'=>7,'₈'=>8,'₉'=>9];
            foreach ($subMatches[0] as $subChar) {
                $str = str_replace($subChar, (string)$subMap[$subChar], $str);
            }
        }

        // Remove any non-digit, non-dot characters
        $str = preg_replace('/[^\d.]/', '', $str);

        if ($str === '') return null;

        return (float) $str;
    }
}
