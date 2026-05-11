<?php

namespace App\Jobs;

use App\Services\Salesforce\SalesforceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;

class SyncSalesforceOpportunitiesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Forrest::authenticate();
        } catch (\Throwable $e) {
            Log::warning('SyncSalesforceOpportunitiesJob: Forrest authentication warning: '.$e->getMessage());
        }

        $result = app(SalesforceService::class)->syncOpportunitiesIncrementally();

        if (($result['success'] ?? false) === true) {
            Log::info('SyncSalesforceOpportunitiesJob: '.$result['message'], $result);

            return;
        }

        Log::error('SyncSalesforceOpportunitiesJob: '.$result['message'], $result);
    }
}
