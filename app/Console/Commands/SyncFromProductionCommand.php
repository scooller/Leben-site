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
                            {--authorized-url= : URL autorizada para el token (ej: http://127.0.0.1:8000/admin)}
                            {--entities=* : Módulos a sincronizar: site_settings, projects, advisors, plants}
                            {--mode=update : Modo de sincronización: update, overwrite, skip}';

    protected $description = 'Descarga e importa la configuración, proyectos y plantas desde producción';

    public function handle(ProductionSyncService $service, ProductionSyncProgressTracker $tracker): int
    {
        $baseUrl = $this->option('base-url') ?: config('services.production_sync.base_url');
        $token = $this->option('token') ?: config('services.production_sync.token');
        $authorizedUrl = $this->option('authorized-url') ?: config('services.production_sync.authorized_url');
        $rawEntities = (array) $this->option('entities');
        $entities = $rawEntities !== [] ? $rawEntities : ['site_settings', 'projects', 'advisors', 'plants'];
        $mode = (string) ($this->option('mode') ?: 'update');

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
        $totalSteps = (in_array('site_settings', $entities, true) ? 1 : 0)
            + (in_array('projects', $entities, true) ? $projectsCount : 0)
            + (in_array('advisors', $entities, true) ? $advisorsCount : 0)
            + (in_array('plants', $entities, true) ? $plantsCount : 0);
        $tracker->initialize($syncId, max(1, $totalSteps), $baseUrl);

        $this->info("Iniciando sincronización local (modo: {$mode})...");
        $result = $service->syncSnapshot($syncId, $snapshot, $tracker, $entities, $mode);
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
