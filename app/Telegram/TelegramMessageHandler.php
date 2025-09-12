<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Services\SolanaCallParser;
use App\Helpers\SlackNotifier;
use Symfony\Component\Process\Process;
use danog\MadelineProto\EventHandler;
use Throwable;

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

        // --- Send Slack message for every incoming message (testing) ---
        SlackNotifier::info("Incoming Telegram message: " . substr($text, 0, 500)); // limit to 500 chars
        SlackNotifier::error('Test');

        // Only process if it matches Solana call phrase
        if (stripos($text, self::SOLANA_CALL_PHRASE) === false) {
            SlackNotifier::error('No memecoin call message');
            return;
        }

        $this->logger("🚀 IMMEDIATE SOLANA CALL DETECTED: Parsing...");
        SlackNotifier::success('🚀 IMMEDIATE SOLANA CALL DETECTED: Parsing...');

        try {
            // Parse and save
            $parser = new SolanaCallParser();
            $call = $parser->parseAndSave($text);
            if (!$call) {
                $this->logger("❌ Failed to parse SolanaCall from message.");
                SlackNotifier::warning("Failed to parse SolanaCall from message.");
                return;
            }

            // Format USD price
            $usdFormatted = number_format((float) ($call->usd_price ?? 0), 6);

            $this->logger("✅ Parsed and saved SolanaCall ID: {$call->id} - Token: {$call->token_name} ({$call->token_address}) - USD: $" . $usdFormatted);
            SlackNotifier::success("Parsed SolanaCall ID: {$call->id} - Token: {$call->token_name} - USD: $" . $usdFormatted);

            // Run the snipe command asynchronously
            $process = new Process([
                'php',
                'artisan',
                'handle-solana-call',
                '--id=' . $call->id,
            ]);
            $process->setTimeout(360);  // 6 mins timeout
            $process->start();

            $this->logger("🔥 Started snipe process for SolanaCall ID: {$call->id} (PID: " . $process->getPid() . ")");
            SlackNotifier::info("Started snipe process for SolanaCall ID: {$call->id} (PID: " . $process->getPid() . ")");

        } catch (Throwable $e) {
            $this->logger("❌ Error processing SolanaCall: " . $e->getMessage());
            SlackNotifier::error("Error processing SolanaCall: " . $e->getMessage());
        }
    }
}
