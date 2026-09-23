<?php

namespace App\Filament\Pages;

use App\Services\ProductionSync\ProductionSyncProgressTracker;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Request;

class ProductionSyncProgress extends Page
{
    protected static ?string $title = 'Progreso de sincronización';

    protected static ?string $navigationLabel = 'Progreso Sync Producción';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.production-sync-progress';

    public ?string $syncId = null;

    /**
     * @var array<string, mixed>
     */
    public array $snapshot = [];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return app()->environment('testing') || app()->environment('local');
    }

    public function mount(): void
    {
        $querySync = Request::query('sync', '');
        $this->syncId = is_scalar($querySync) ? trim((string) $querySync) : '';
        $this->refreshProgress();
    }

    public function refreshProgress(): void
    {
        if ($this->syncId === null || $this->syncId === '') {
            $this->snapshot = [
                'status' => 'not_found',
                'logs' => [],
            ];

            return;
        }

        $tracker = app(ProductionSyncProgressTracker::class);
        $this->snapshot = $tracker->snapshot($this->syncId);

        $this->checkTimeout($tracker);
    }

    public function checkTimeout(ProductionSyncProgressTracker $tracker): void
    {
        if (($this->snapshot['status'] ?? '') !== 'running') {
            return;
        }

        $startedAt = $this->snapshot['started_at'] ?? null;
        if (blank($startedAt)) {
            return;
        }

        $timeoutSeconds = max(30, (int) config('services.production_sync.timeout', 120));

        if (Carbon::parse($startedAt)->addSeconds($timeoutSeconds)->isPast()) {
            $errorMessage = "Tiempo de espera agotado (timeout de {$timeoutSeconds}s). La sincronización no respondió dentro del límite esperado o el worker de colas se detuvo.";
            $tracker->markFailed($this->syncId, $errorMessage);
            $this->snapshot = $tracker->snapshot($this->syncId);
        }
    }
}
