<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Services\SolanaCallParser;
use danog\MadelineProto\EventHandler;

class TelegramMessageHandler extends EventHandler
{
    private const SOLANA_CALL_PHRASE = '🔥 First Call 🚀 SOLANA100XCALL • Premium Signals @';

    /**
     * Handle new messages in private chats/small groups (legacy v8 signature).
     */
    public function onUpdateNewMessage(array $update): void
    {
        $this->processMessage($update);
    }

    /**
     * Handle new messages in supergroups/channels (legacy v8 signature).
     */
    public function onUpdateNewChannelMessage(array $update): void
    {
        $this->processMessage($update);
    }

    /**
     * Process the update and check for Solana call.
     */
    private function processMessage(array $update): void
    {
        $text = $update['message']['message'] ?? '';
        if (stripos($text, self::SOLANA_CALL_PHRASE) === false) {
            return;  // Ignore if not a Solana call
        }

        var_dump($text);

        $chatId = $this->getId($update['message']['peer_id']);  // Resolve peer to ID
        $this->logger("🚀 IMMEDIATE SOLANA CALL DETECTED in chat {$chatId}: Parsing...");

        // Parse and save
        $parser = new SolanaCallParser();
        $call = $parser->parseAndSave($text);
        if ($call) {
            // Fix: Cast to float before number_format to handle string decimals from model casts
            $usdFormatted = number_format((float) ($call->usd_price ?? 0), 6);
            $this->logger("✅ Parsed and saved SolanaCall: {$call->token_name} ({$call->token_address}) - USD: $" . $usdFormatted);
        } else {
            $this->logger("❌ Failed to parse SolanaCall from message.");
        }

        // Your other logic (e.g., notifications)
    }
}
