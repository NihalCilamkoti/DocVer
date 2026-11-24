<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled temporary data privacy purge (runs hourly in production)
Schedule::command('docver:purge-temporary')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
