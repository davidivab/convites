<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// DB backup every 4 hours at :05 — offset from midnight jobs.
Schedule::command('db:backup')
    ->cron('5 */4 * * *')
    ->runInBackground()
    ->withoutOverlapping()
    ->onOneServer()
    ->onFailure(function () {
        Log::error('Scheduled db:backup failed');
    });
