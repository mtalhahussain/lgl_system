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

