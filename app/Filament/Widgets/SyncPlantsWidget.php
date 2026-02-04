<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\SyncPlantsAction;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class SyncPlantsWidget extends Widget
{
    protected string $view = 'filament.widgets.sync-plants-widget';

    protected static ?int $sort = 1;

    public ?string $lastSyncTime = null;
    public int $totalPlants = 0;
    public int $activePlants = 0;
    public bool $isSyncing = false;

    public function mount(): void
    {
        $this->loadStats();
    }

    public function loadStats(): void
    {
        $this->totalPlants = SyncPlantsAction::getTotalPlants();
        $this->activePlants = SyncPlantsAction::getActivePlants();

        $lastSync = SyncPlantsAction::getLastSyncTime();
        $this->lastSyncTime = $lastSync ? $lastSync->diffForHumans() : 'Nunca';
    }

    public function syncPlants(): void
    {
        $this->isSyncing = true;
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
        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isSyncing = false;
            $this->dispatch('sync-completed');
        }
    }
}
