<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register mobile money commands - Laravel 11 auto-discovers commands
// The command will be automatically registered from the Commands directory

// Schedule the pending transactions query to run every 5 minutes
Schedule::command('mobile-money:query-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Schedule auto-validation processing to run every hour
Schedule::command('worksite:process-auto-validations')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Schedule system health monitoring to run every 5 minutes
Schedule::command('monitor:health --alert')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
