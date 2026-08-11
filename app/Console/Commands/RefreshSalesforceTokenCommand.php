<?php

namespace App\Console\Commands;

use App\Services\Salesforce\SalesforceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Throwable;

class RefreshSalesforceTokenCommand extends Command
{
    protected $signature = 'salesforce:refresh-token';

    protected $description = 'Renueva proactivamente el access token de Salesforce OAuth usando el refresh token. No requiere intervención del usuario.';

    public function handle(SalesforceService $salesforceService): int
    {
        $leadEnabled = (bool) config('services.salesforce.lead_enabled', false);

        if (! $leadEnabled) {
            $this->line('Salesforce deshabilitado (SF_LEAD_ENABLED=false). Nada que hacer.');

            return self::SUCCESS;
        }

        try {
            if (Forrest::hasToken()) {
                if ($salesforceService->isTokenExpiringSoon()) {
                    $refreshed = $salesforceService->proactiveRefresh();

                    if ($refreshed) {
                        Log::info('salesforce:refresh-token - Token renovado proactivamente (estaba próximo a expirar).');
                        $this->info('Token renovado proactivamente. Backup actualizado en DB.');
                    } else {
                        Log::warning('salesforce:refresh-token - Refresh proactivo fallido. Token puede expirar pronto.');
                        $this->warn('Refresh proactivo fallido. El token puede expirar pronto.');
                    }
                } else {
                    Log::info('salesforce:refresh-token - Token vigente. Backup en DB actualizado.');
                    $this->info('Token vigente. Backup actualizado en DB correctamente.');
                }

                $salesforceService->updateTokenBackup();

                return self::SUCCESS;
            }

            // Token no está en caché — intentar recuperar desde backup en DB
            $this->warn('Token no encontrado en caché. Intentando auto-reconexión desde backup en DB...');
            $reconnected = $salesforceService->tryAutoReconnect();

            if ($reconnected) {
                Log::info('salesforce:refresh-token - Auto-reconexión exitosa desde backup en DB.');
                $this->info('Auto-reconexión exitosa. Token restaurado y renovado.');

                return self::SUCCESS;
            }

            Log::critical('salesforce:refresh-token - Auto-reconexión fallida. No hay backup en DB o el refresh token expiró. Reconexión manual requerida en /admin/site-settings.');
            $salesforceService->markAsDisconnected('salesforce:refresh-token - auto-reconexión fallida: sin token en caché ni backup válido en DB.');
            $this->error('Auto-reconexión fallida. Se requiere reconexión manual en /admin/site-settings → "Conectar con Salesforce".');

            return self::FAILURE;
        } catch (Throwable $e) {
            $errorMessage = strtolower($e->getMessage());

            if (str_contains($errorMessage, 'invalid_grant') && str_contains($errorMessage, 'expired access/refresh token')) {
                Log::critical('salesforce:refresh-token - Refresh token expirado o revocado (invalid_grant). Reconexión manual requerida.', [
                    'error' => $e->getMessage(),
                ]);
                $salesforceService->markAsDisconnected('salesforce:refresh-token - invalid_grant: expired access/refresh token');
                $this->error('El refresh token de Salesforce expiró o fue revocado. Reconecta en /admin/site-settings → "Conectar con Salesforce".');

                return self::FAILURE;
            }

            Log::error('salesforce:refresh-token - Error inesperado.', ['error' => $e->getMessage()]);
            $this->error('Error inesperado: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
