<?php

namespace App\Jobs;

use App\Filament\Actions\SyncPlantsAction;
use App\Services\Salesforce\SalesforceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;

class SyncPlantsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $salesforceService = app(SalesforceService::class);

        if (! Forrest::hasToken()) {
            if (! $salesforceService->tryAutoReconnect()) {
                Log::warning('SyncPlantsJob: Token de Salesforce no disponible y auto-reconexión fallida. Omitiendo sincronización.');

                return;
            }
        }

        // Si el token está próximo a expirar, refrescar proactivamente
        if ($salesforceService->isTokenExpiringSoon()) {
            $salesforceService->proactiveRefresh();
        }

        $result = SyncPlantsAction::execute();

        if ($result['success']) {
            Log::debug('SyncPlantsJob: ' . $result['message']);
        } else {
            Log::error('SyncPlantsJob: ' . $result['message']);
        }
    }
}
