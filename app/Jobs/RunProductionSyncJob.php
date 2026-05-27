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

    public function __construct(public string $syncId) {}

    public function handle(ProductionSyncService $service, ProductionSyncProgressTracker $tracker): void
    {
        $tracker->addLog($this->syncId, 'Descargando datos desde producción.');
        $snapshot = $service->fetchSnapshot();

        $error = trim((string) data_get($snapshot, 'meta.error', ''));

        if ($error !== '') {
            $tracker->markFailed($this->syncId, $error);

            return;
        }

        $totalSteps = 1 + count((array) ($snapshot['projects'] ?? [])) + count((array) ($snapshot['plants'] ?? []));
        $tracker->setTotalSteps($this->syncId, $totalSteps);
        $tracker->addLog($this->syncId, 'Sincronización iniciada.');

        $result = $service->syncSnapshot($this->syncId, $snapshot, $tracker);

        $tracker->markCompleted($this->syncId);
        $tracker->addLog(
            $this->syncId,
            sprintf(
                'Sincronización finalizada. Configuración: %s. Proyectos: %d creados, %d actualizados. Plantas: %d creadas, %d actualizadas.',
                $result['site_settings'],
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
