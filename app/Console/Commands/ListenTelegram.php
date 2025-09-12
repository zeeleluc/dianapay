<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Peer as PeerSettings;
use App\Telegram\TelegramMessageHandler;
use Throwable;

class ListenTelegram extends Command
{
    protected $signature = 'telegram:listen';
    protected $description = 'Listen for new messages in Telegram using MadelineProto (user login with API ID/Hash)';

    public function handle(): int
    {
        $this->info('Starting Telegram listener...');

        // Global fallback error handler for leaked deprecations (PHP 8.4+ only)
        $originalErrorHandler = null;
        if (PHP_VERSION_ID >= 80400) {
            $originalErrorHandler = set_error_handler(function (int $severity, string $message, string $file, int $line) use (&$originalErrorHandler): bool {
                if (($severity & E_DEPRECATED) === 0 || strpos($message, 'BaconQrCode\\Encoder\\Encoder::chooseMode') === false) {
                    if ($originalErrorHandler) {
                        return call_user_func($originalErrorHandler, $severity, $message, $file, $line);
                    }
                    return false;
                }
                $this->warn("Suppressed BaconQrCode deprecation during startup: {$message}");
                return true; // Explicitly return true to suppress
            });
        }

        $madelineProto = null;
        try {
            $settings = new Settings();
            $settings->getLogger()->setLevel(Logger::ERROR); // ERROR for production; change to VERBOSE for MinDatabase debugging
            $appInfo = new AppInfo();
            $appInfo->setApiId((int) env('TELEGRAM_API_ID'));
            $appInfo->setApiHash(env('TELEGRAM_API_HASH'));
            $appInfo->setShowPrompt(false); // Non-interactive (we'll handle prompts manually)
            $settings->setAppInfo($appInfo);

            // Enable full peer caching on startup to populate MinDatabase (helps with user filtering)
            $peerSettings = new PeerSettings();
            $peerSettings->setCacheAllPeersOnStartup(true);
            $settings->setPeer($peerSettings);

            // Create API instance
            $madelineProto = new API(storage_path('telegram.session'), $settings);

            $this->info('Using user login with TELEGRAM_API_ID and TELEGRAM_API_HASH...');

            // Handle user phone login interactively if needed (first run or invalid session)
            $authorizationState = $madelineProto->getAuthorization();
            if ($authorizationState === API::NOT_LOGGED_IN) {
                $phone = $this->ask('Enter phone number (e.g., +1234567890)');
                $madelineProto->phoneLogin($phone);

                $authorization = $madelineProto->completePhoneLogin($this->ask('Enter verification code received'));

                if ($authorization['_'] === 'account.password') {
                    $password = $this->secret('Enter 2FA password (hint: ' . ($authorization['hint'] ?? 'none') . ')');
                    $madelineProto->complete2faLogin($password);
                } elseif ($authorization['_'] === 'account.needSignup') {
                    $firstName = $this->ask('Enter first name');
                    $lastName = $this->ask('Enter last name (optional)', '');
                    $madelineProto->completeSignup($firstName, $lastName);
                }
            }

            // Pre-cache Phanes' user peer to avoid pending contexts on first message
            try {
                $userPeer = $madelineProto->getPwrChat(7178305557);  // Cache user ID
                // Fix: Use ternary instead of ?? for PHP <7.0 compatibility
                $username = isset($userPeer['username']) ? $userPeer['username'] : 'N/A';
                $this->info("Pre-cached Phanes (ID: 7178305557) - username: {$username}");
            } catch (Throwable $e) {
                $this->warn("Failed to pre-cache Phanes: " . $e->getMessage() . " (may cause initial pending context)");
            }

            // Start API: Wrap in local handler if on PHP 8.4+ to suppress QR deprecation
            if (PHP_VERSION_ID >= 80400) {
                // Fix: Capture the return value of set_error_handler (previous handler)
                $tempHandler = set_error_handler(function (int $severity, string $message, string $file, int $line) use ($originalErrorHandler): bool {
                    if (($severity & E_DEPRECATED) === 0 || strpos($message, 'BaconQrCode\\Encoder\\Encoder::chooseMode') === false) {
                        if ($originalErrorHandler) {
                            return call_user_func($originalErrorHandler, $severity, $message, $file, $line);
                        }
                        return false;
                    }
                    $this->warn("Suppressed BaconQrCode deprecation in start(): {$message}");
                    return true; // Explicitly return true to suppress
                });
                $madelineProto->start();
                set_error_handler($tempHandler); // Restore previous handler
            } else {
                $madelineProto->start();
            }

            // Restore global handler
            if (PHP_VERSION_ID >= 80400 && $originalErrorHandler) {
                set_error_handler($originalErrorHandler);
            }

            $this->info('Telegram listener started successfully. API state: ' . $this->formatAuthState($madelineProto->getAuthorization()));
            $this->info('Press Ctrl+C to stop.');

            // Start the event loop
            API::startAndLoopMulti(
                [$madelineProto],
                TelegramMessageHandler::class
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            // Restore global handler
            if (PHP_VERSION_ID >= 80400 && $originalErrorHandler) {
                set_error_handler($originalErrorHandler);
            }

            $this->error('Failed to start Telegram listener: ' . $e->getMessage());
            if ($madelineProto) {
                $state = $madelineProto->getAuthorization() ?? 'Unknown';
                $this->error('API instance created but failed during start. State: ' . $this->formatAuthState($state));
            }
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * Format authorization state constant to readable string.
     */
    private function formatAuthState(int $state): string
    {
        return match ($state) {
            API::LOGGED_IN => 'Logged in',
            API::WAITING_CODE => 'Waiting for code',
            API::WAITING_PASSWORD => 'Waiting for password',
            API::NOT_LOGGED_IN => 'Not logged in',
            API::WAITING_SIGNUP => 'Waiting for signup',
            API::LOGGED_OUT => 'Logged out',
            default => 'Unknown (' . $state . ')'
        };
    }
}
