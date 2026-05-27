<?php

namespace App\Filament\Actions;

use App\Filament\Pages\ProductionSyncProgress;
use App\Jobs\RunProductionSyncJob;
use App\Services\ProductionSync\ProductionSyncProgressTracker;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class SyncFromProductionAction
{
    public static function make(): Action
    {
        return Action::make('sync_from_production')
            ->label('Sincronizar desde producción')
            ->icon('heroicon-o-cloud-arrow-down')
            ->color('warning')
            ->visible(fn(): bool => self::isAvailable())
            ->requiresConfirmation()
            ->modalHeading('Sincronizar desde producción')
            ->modalDescription('Se consumirá la API de producción con token Bearer. El proceso se ejecutará en segundo plano y podrás revisar el avance en una vista de progreso.')
            ->modalSubmitActionLabel('Iniciar sincronización')
            ->action(function (): void {
                $syncId = (string) Str::uuid();
                $baseUrl = trim((string) config('services.production_sync.base_url', ''));

                app(ProductionSyncProgressTracker::class)->initialize($syncId, 0, $baseUrl);
                app(ProductionSyncProgressTracker::class)->addLog($syncId, 'Sincronización solicitada desde el panel.');

                RunProductionSyncJob::dispatch($syncId);

                $progressUrl = ProductionSyncProgress::getUrl(['sync' => $syncId]);

                Notification::make()
                    ->title('Sincronización iniciada')
                    ->body('La sincronización se ejecutó en segundo plano. Puedes seguir el progreso en la vista dedicada.')
                    ->success()
                    ->persistent()
                    ->actions([
                        Action::make('viewProductionSyncProgress')
                            ->label('Ver progreso')
                            ->button()
                            ->url($progressUrl, shouldOpenInNewTab: true),
                    ])
                    ->send();
            });
    }

    public static function isAvailable(): bool
    {
        return app()->environment('testing')
            && filled(config('services.production_sync.base_url'))
            && filled(config('services.production_sync.token'));
    }
}
