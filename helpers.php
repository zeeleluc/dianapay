<?php

use Illuminate\Support\Facades\Session;

if (!function_exists('get_locale')) {
    function get_locale()
    {
        return Session::get('locale', config('app.locale'));
    }
}

if (!function_exists('cur_to_utc')) {
    /**
     * Convert a time string from Curaçao (AST, UTC-4) to UTC for Laravel scheduler.
     *
     * @param string $curacaoTime Time in Curaçao (e.g., '6:00', '13:05')
     * @return string UTC time in 24-hour format (e.g., '10:00', '17:05')
     */
    function cur_to_utc(string $curacaoTime): string
    {
        // Create a Carbon instance for today in Curaçao's timezone (AST)
        $dateTime = \Carbon\Carbon::createFromFormat('H:i', $curacaoTime, 'America/Curacao');

        // Convert to UTC
        $dateTime->setTimezone('UTC');

        // Return formatted time (e.g., '10:00')
        return $dateTime->format('H:i');
    }
}
