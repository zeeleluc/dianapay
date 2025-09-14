<?php

use Illuminate\Console\Scheduling\Schedule;

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
    $schedule->command('solana:poll-solana-tokens')->everyThirtySeconds();
    $schedule->command('solana:auto-sell')->everyThirtySeconds();
    $schedule->command('solana:buy-new-solana-tokens')->everyThirtySeconds();
}
