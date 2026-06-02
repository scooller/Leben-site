<?php

use App\Jobs\SyncPlantsJob;
use App\Models\FrontendPreviewLink;
use App\Support\SalesforcePlantSyncSchedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
	$this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

try {
	Schedule::command('reservations:expire')->everyMinute();
	Schedule::command('model:prune', [
		'--model' => [FrontendPreviewLink::class],
	])->daily();
	Schedule::job(new SyncPlantsJob)
		->everyMinute()
		->withoutOverlapping()
		->when(static fn(): bool => SalesforcePlantSyncSchedule::shouldRunAt());
	Schedule::command('salesforce:refresh-token')->cron('0 */20 * * *')->withoutOverlapping();
} catch (\Throwable $exception) {
	// Allow first-time installs to run migrations before settings-backed packages are ready.
	Log::error('Error: ' . $exception->getMessage());
}
