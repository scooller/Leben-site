<?php

namespace App\Console\Commands;

use App\Services\ProductionSync\ProductionSyncProgressTracker;
use App\Services\ProductionSync\ProductionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncFromProductionCommand extends Command
{
    protected $signature = 'production:sync
                            {--base-url= : URL base de producción (ej: https://admin.ileben.cl)}
                            {--token= : Token Bearer Sanctum}
                            {--authorized-url= : URL autorizada para el token (ej: http://127.0.0.1:8000/admin)}';

    protected $description = 'Descarga e importa la configuración, proyectos y plantas desde producción';

    public function handle(ProductionSyncService $service, ProductionSyncProgressTracker $tracker): int
    {
        $baseUrl = $this->option('base-url') ?: config('services.production_sync.base_url');
        $token = $this->option('token') ?: config('services.production_sync.token');
        $authorizedUrl = $this->option('authorized-url') ?: config('services.production_sync.authorized_url');

        if (blank($baseUrl) || blank($token)) {
            $this->error('Falta configurar PRODUCTION_SYNC_BASE_URL o PRODUCTION_SYNC_TOKEN (en .env o por opciones --base-url / --token).');

            return self::FAILURE;
        }

        $this->info("Descargando snapshot desde {$baseUrl}...");
        $snapshot = $service->fetchSnapshot($baseUrl, $token, $authorizedUrl);

        $error = trim((string) data_get($snapshot, 'meta.error', ''));
        if ($error !== '') {
            $this->error("Error al obtener datos: {$error}");

            return self::FAILURE;
        }

        $projectsCount = count((array) ($snapshot['projects'] ?? []));
        $advisorsCount = count((array) ($snapshot['advisors'] ?? []));
        $plantsCount = count((array) ($snapshot['plants'] ?? []));
        $this->info("Datos recibidos: {$projectsCount} proyectos, {$advisorsCount} asesores, {$plantsCount} plantas.");

        $syncId = (string) Str::uuid();
        $totalSteps = 1 + $projectsCount + $advisorsCount + $plantsCount;
        $tracker->initialize($syncId, $totalSteps, $baseUrl);

        $this->info('Iniciando sincronización local...');
        $result = $service->syncSnapshot($syncId, $snapshot, $tracker);
        $tracker->markCompleted($syncId);

        $this->newLine();
        $this->info('Sincronización completada con éxito:');
        $this->line("- Configuración del sitio: {$result['site_settings']}");
        $this->line("- Asesores: {$result['advisors']['created']} creados, {$result['advisors']['updated']} actualizados, {$result['advisors']['skipped']} omitidos");
        $this->line("- Proyectos: {$result['projects']['created']} creados, {$result['projects']['updated']} actualizados, {$result['projects']['skipped']} omitidos");
        $this->line("- Plantas: {$result['plants']['created']} creadas, {$result['plants']['updated']} actualizadas, {$result['plants']['skipped']} omitidas");

        return self::SUCCESS;
    }
}
