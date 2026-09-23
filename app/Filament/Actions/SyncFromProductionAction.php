<?php

namespace App\Filament\Actions;

use App\Filament\Pages\ProductionSyncProgress;
use App\Jobs\RunProductionSyncJob;
use App\Services\ProductionSync\ProductionSyncProgressTracker;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class SyncFromProductionAction
{
    public static function make(): Action
    {
        $defaultBaseUrl = trim((string) config('services.production_sync.base_url', 'https://admin.ileben.cl'));
        $defaultToken = trim((string) config('services.production_sync.token', ''));
        $defaultAuthUrl = trim((string) config('services.production_sync.authorized_url', 'http://127.0.0.1:8000/admin'));

        return Action::make('sync_from_production')
            ->label('Sincronizar desde producción')
            ->icon('heroicon-o-cloud-arrow-down')
            ->color('warning')
            ->visible(fn (): bool => self::isAvailable())
            ->modalHeading('Sincronizar desde producción')
            ->modalDescription('Se consumirá la API de producción con token Bearer. Puedes ejecutarlo directamente de forma inmediata o en segundo plano.')
            ->modalSubmitActionLabel('Iniciar sincronización')
            ->form([
                TextInput::make('base_url')
                    ->label('URL de producción')
                    ->default($defaultBaseUrl)
                    ->required(),
                TextInput::make('token')
                    ->label('Token API de producción')
                    ->password()
                    ->revealable()
                    ->default($defaultToken)
                    ->required()
                    ->helperText('Token Sanctum generado en Producción (Admin -> API Tokens).'),
                TextInput::make('authorized_url')
                    ->label('URL autorizada / Origen')
                    ->default($defaultAuthUrl ?: 'http://127.0.0.1:8000/admin')
                    ->required()
                    ->helperText('URL autorizada configurada al crear el token en producción (ej: http://127.0.0.1:8000/admin).'),
                Toggle::make('run_in_background')
                    ->label('Ejecutar en segundo plano')
                    ->helperText('Actívalo solo si tienes "php artisan queue:work" ejecutándose en tu terminal. Si está desactivado, sincroniza inmediatamente.')
                    ->default(true),
            ])
            ->action(function (array $data): void {
                $syncId = (string) Str::uuid();
                $baseUrl = trim((string) ($data['base_url'] ?? config('services.production_sync.base_url', '')));
                $token = trim((string) ($data['token'] ?? config('services.production_sync.token', '')));
                $authorizedUrl = trim((string) ($data['authorized_url'] ?? config('services.production_sync.authorized_url', '')));
                $runInBackground = (bool) ($data['run_in_background'] ?? false);

                app(ProductionSyncProgressTracker::class)->initialize($syncId, 0, $baseUrl);
                app(ProductionSyncProgressTracker::class)->addLog($syncId, 'Sincronización solicitada desde el panel.');

                if ($runInBackground) {
                    RunProductionSyncJob::dispatch($syncId, $baseUrl, $token, $authorizedUrl);
                } else {
                    RunProductionSyncJob::dispatchSync($syncId, $baseUrl, $token, $authorizedUrl);
                }

                $progressUrl = ProductionSyncProgress::getUrl(['sync' => $syncId]);

                Notification::make()
                    ->title($runInBackground ? 'Sincronización iniciada en segundo plano' : 'Sincronización finalizada')
                    ->body($runInBackground
                        ? 'La sincronización se envió a la cola. Puedes seguir el avance en la vista dedicada.'
                        : 'El proceso se ejecutó inmediatamente. Revisa el resultado y registros en la vista dedicada.')
                    ->success()
                    ->persistent()
                    ->actions([
                        Action::make('viewProductionSyncProgress')
                            ->label('Ver detalle y logs')
                            ->button()
                            ->url($progressUrl, shouldOpenInNewTab: true),
                    ])
                    ->send();
            });
    }

    public static function isAvailable(): bool
    {
        return app()->environment('testing') || app()->environment('local');
    }
}
