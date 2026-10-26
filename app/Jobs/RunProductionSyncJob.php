<?php

namespace App\Jobs;

use App\Services\ProductionSync\ProductionSyncProgressTracker;
use App\Services\ProductionSync\ProductionSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunProductionSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $syncId = '';

    public ?string $baseUrl = null;

    public ?string $token = null;

    public ?string $authorizedUrl = null;

    /**
     * @var list<string>
     */
    public array $entities = ['site_settings', 'projects', 'advisors', 'plants'];

    public string $mode = 'update';

    /**
     * @param  list<string>  $entities
     */
    public function __construct(
        string $syncId,
        ?string $baseUrl = null,
        ?string $token = null,
        ?string $authorizedUrl = null,
        array $entities = ['site_settings', 'projects', 'advisors', 'plants'],
        string $mode = 'update',
    ) {
        $this->syncId = $syncId;
        $this->baseUrl = $baseUrl;
        $this->token = $token;
        $this->authorizedUrl = $authorizedUrl;
        $this->entities = $entities ?: ['site_settings', 'projects', 'advisors', 'plants'];
        $this->mode = in_array($mode, ['update', 'overwrite', 'skip'], true) ? $mode : 'update';
    }

    public function handle(ProductionSyncService $service, ProductionSyncProgressTracker $tracker): void
    {
        $tracker->addLog($this->syncId, 'Descargando datos desde producción.');
        $snapshot = $service->fetchSnapshot($this->baseUrl, $this->token, $this->authorizedUrl);

        $error = trim((string) data_get($snapshot, 'meta.error', ''));

        if ($error !== '') {
            $tracker->markFailed($this->syncId, $error);

            return;
        }

        $totalSteps = (in_array('site_settings', $this->entities, true) ? 1 : 0)
            + (in_array('projects', $this->entities, true) ? count((array) ($snapshot['projects'] ?? [])) : 0)
            + (in_array('advisors', $this->entities, true) ? count((array) ($snapshot['advisors'] ?? [])) : 0)
            + (in_array('plants', $this->entities, true) ? count((array) ($snapshot['plants'] ?? [])) : 0);
        $tracker->setTotalSteps($this->syncId, max(1, $totalSteps));
        $tracker->addLog($this->syncId, "Sincronización iniciada (Modo: {$this->mode}).");

        $result = $service->syncSnapshot($this->syncId, $snapshot, $tracker, $this->entities, $this->mode);

        $tracker->markCompleted($this->syncId);
        $tracker->addLog(
            $this->syncId,
            sprintf(
                'Sincronización finalizada. Configuración: %s. Asesores: %d creados, %d actualizados. Proyectos: %d creados, %d actualizados. Plantas: %d creadas, %d actualizadas.',
                $result['site_settings'],
                $result['advisors']['created'],
                $result['advisors']['updated'],
                $result['projects']['created'],
                $result['projects']['updated'],
                $result['plants']['created'],
                $result['plants']['updated'],
            )
        );
    }

    public function failed(mixed $exception): void
    {
        $message = is_string($exception)
            ? $exception
            : 'Fallo inesperado en sync de producción.';

        app(ProductionSyncProgressTracker::class)->markFailed($this->syncId, $message);
    }
}
