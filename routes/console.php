<?php

use App\Jobs\SyncPlantsJob;
use App\Models\FrontendPreviewLink;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

try {
    Schedule::command('reservations:expire')->everyMinute();
    Schedule::command('model:prune', [
        '--model' => [FrontendPreviewLink::class],
    ])->daily();
    Schedule::job(new SyncPlantsJob)->everyFiveMinutes()->withoutOverlapping();
    Schedule::command('salesforce:refresh-token')->everyTwentyHours()->withoutOverlapping();
} catch (Throwable) {
} catch (\Throwable) {
    // Allow first-time installs to run migrations before settings-backed packages are ready.
}
