<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\SyncProjectsAction;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class SyncProjectsWidget extends Widget
{
    protected string $view = 'filament.widgets.sync-projects-widget';

    protected static ?int $sort = 2;

    public ?string $lastSyncTime = null;
    public int $totalProjects = 0;
    public bool $isSyncing = false;

    public function mount(): void
    {
        $this->loadStats();
    }

    public function loadStats(): void
    {
        $this->totalProjects = SyncProjectsAction::getTotalProjects();

        $lastSync = SyncProjectsAction::getLastSyncTime();
        $this->lastSyncTime = $lastSync ? $lastSync->diffForHumans() : 'Nunca';
    }

    public function syncProjects(): void
    {
        $this->isSyncing = true;
        $this->dispatch('sync-started');

        try {
            $result = SyncProjectsAction::execute();

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
