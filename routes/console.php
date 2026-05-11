<?php

use App\Jobs\SyncPlantsJob;
use App\Jobs\SyncSalesforceOpportunitiesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

foreach (
    [
        static fn () => Schedule::command('reservations:expire')->everyMinute(),
        static fn () => Schedule::job(new SyncPlantsJob)->everyFiveMinutes()->withoutOverlapping(),
        static fn () => Schedule::job(new SyncSalesforceOpportunitiesJob)->hourly()->withoutOverlapping(),
        static fn () => Schedule::command('salesforce:sync-broker-metrics')->everyFifteenMinutes()->withoutOverlapping(),
    ] as $registerSchedule
) {
    try {
        $registerSchedule();
    } catch (\Throwable) {
        // Allow first-time installs to run migrations before settings-backed packages are ready.
    }
}
