<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\AutoMarkAbsentCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatic absence/device processing is deferred; attendance remains manual.

// Requires the host cron entry: * * * * * php artisan schedule:run
Schedule::command('batches:sync-status')->dailyAt('00:05')->withoutOverlapping();
// Once per batch: admins are told a week ahead when a teacher's batch ends without a next batch assigned.
Schedule::command('batches:ending-alerts')->dailyAt('08:00')->withoutOverlapping();
// Does nothing until Admin switches reminders on and WHATSAPP_* credentials are set.
Schedule::command('fees:whatsapp-reminders')->dailyAt('10:00')->withoutOverlapping();

