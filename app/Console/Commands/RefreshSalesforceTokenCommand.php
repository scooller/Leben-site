<?php

namespace App\Console\Commands;

use App\Services\Salesforce\SalesforceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;

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
                // Token presente en caché — refresh proactivo antes de que expire
                $this->info('Token en caché. Realizando refresh proactivo...');
                Forrest::refresh();
                $salesforceService->updateTokenBackup();

                Log::info('salesforce:refresh-token - Refresh proactivo exitoso.');
                $this->info('Token renovado y backup actualizado en DB correctamente.');

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
            $this->error('Auto-reconexión fallida. Se requiere reconexión manual en /admin/site-settings → "Conectar con Salesforce".');

            return self::FAILURE;
        } catch (\Throwable $e) {
            Log::error('salesforce:refresh-token - Error inesperado.', ['error' => $e->getMessage()]);
            $this->error('Error inesperado: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
