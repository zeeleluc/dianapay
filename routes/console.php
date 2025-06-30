<?php

use Illuminate\Console\Scheduling\Schedule;

$schedule = app(Schedule::class);

// ========== Production ==========
if (app()->environment('prod')) {
    $schedule->command('crypto:fetch-rates')->everyTwoMinutes();
}
