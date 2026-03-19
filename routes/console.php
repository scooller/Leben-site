<?php

use App\Jobs\SyncPlantsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reservations:expire')->everyMinute();
Schedule::job(new SyncPlantsJob)->dailyAt('03:00');
