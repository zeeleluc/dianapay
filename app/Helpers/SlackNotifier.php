<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class SlackNotifier
{
    protected static $webhookUrl;

    protected static $icons = [
        'info' => 'ℹ️',
        'success' => '✅',
        'error' => '❌',
        'warning' => '⚠️',
    ];

    public static function send(string $type, string $message): void
    {
        self::$webhookUrl = config('services.slack.webhook_url');

        if (!self::$webhookUrl) {
            logger()->warning('Slack webhook URL not configured.');
            return;
        }

        $icon = self::$icons[$type] ?? '💬';

        $env = strtoupper(env('APP_ENV'));
        Http::post(self::$webhookUrl, [
            'text' => "`{$env}` {$icon} {$message}",
        ]);
    }

    public static function info(string $message): void
    {
        self::send('info', $message);
    }

    public static function success(string $message): void
    {
        self::send('success', $message);
    }

    public static function error(string $message): void
    {
        self::send('error', $message);
    }

    public static function warning(string $message): void
    {
        self::send('warning', $message);
    }
}
