<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;

$schedule = app(Schedule::class);

// ========== Production ==========
if (app()->environment('prod')) {
    $schedule->command('crypto:fetch-rates')->everyTwoMinutes();
}

// ========== Production-Only Schedule ==========
if (app()->environment('prod')) {
    $schedule->command('tweet:post "GM Crypto Degens 🪙"')->dailyAt(cur_to_utc('6:00'));
}

// ========== Testing/High-Frequency Poll ==========
if (app()->environment('prod')) {
    $schedule->command('solana:clean-failed-calls')->everyMinute();
    $schedule->command('solana:poll-highend')->everyThirtySeconds()->withoutOverlapping();
    $schedule->command('solana:auto-sell')->everyTwoSeconds()->withoutOverlapping();
}
