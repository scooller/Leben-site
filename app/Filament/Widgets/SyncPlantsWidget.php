<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\SyncPlantsAction;
use App\Models\Proyecto;
use Exception;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class SyncPlantsWidget extends Widget
{
    protected string $view = 'filament.widgets.sync-plants-widget';

    protected int|string|array $columnSpan = [
        'md' => 1,
    ];

    public int $totalPlants = 0;

    public int $activePlants = 0;

    public int $totalProyectos = 0;

    public function mount(): void
    {
        $this->loadStats();
    }

    public function loadStats(): void
    {
        $this->totalPlants = SyncPlantsAction::getTotalPlants();
        $this->activePlants = SyncPlantsAction::getActivePlants();
        $this->totalProyectos = Proyecto::count();

        $lastSync = SyncPlantsAction::getLastSyncTime();
        $this->lastSyncTime = $lastSync ? $lastSync->diffForHumans() : 'Nunca';
    }

    public function syncPlants(): void
    {
        if ($this->totalProyectos === 0) {
            Notification::make()
                ->title('⚠️ No hay proyectos')
                ->body('Debes importar proyectos antes de sincronizar plantas.')
                ->warning()
                ->send();

            return;
        }

        $this->dispatch('sync-started');

        try {
            $result = SyncPlantsAction::execute();

            if ($result['success']) {
                Notification::make()
                    ->title('✅ Sincronización exitosa')
                    ->body($result['message'])
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('❌ Error en sincronización')
                    ->body($result['message'])
                    ->danger()
                    ->send();
            }

            $this->loadStats();
        } catch (Exception $e) {
            Notification::make()
                ->title('❌ Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->dispatch('sync-completed');
        }
    }
}
