<?php

namespace App\Filament\Widgets;

use App\Models\SalesforceOpportunity;
use App\Services\Salesforce\SalesforceService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class SyncSalesforceOpportunitiesWidget extends Widget
{
    protected string $view = 'filament.widgets.sync-salesforce-opportunities-widget';

    protected int|string|array $columnSpan = [
        'md' => 1,
    ];

    public int $totalOpportunities = 0;

    public int $openOpportunities = 0;

    public string $lastSyncTime = 'Nunca';

    public function mount(): void
    {
        $this->loadStats();
    }

    public function loadStats(): void
    {
        $this->totalOpportunities = SalesforceOpportunity::query()->count();
        $this->openOpportunities = SalesforceOpportunity::query()
            ->where('is_closed', false)
            ->count();

        $lastSync = SalesforceOpportunity::query()
            ->whereNotNull('synced_at')
            ->latest('synced_at')
            ->value('synced_at');

        if (is_string($lastSync) && $lastSync !== '') {
            $this->lastSyncTime = Carbon::parse($lastSync)->diffForHumans();

            return;
        }

        $this->lastSyncTime = 'Nunca';
    }

    public function syncOpportunities(): void
    {
        $this->dispatch('sync-started');

        try {
            $result = app(SalesforceService::class)->syncOpportunitiesIncrementally();

            if (($result['success'] ?? false) === true) {
                Notification::make()
                    ->title('Sincronizacion de oportunidades completada')
                    ->body((string) ($result['message'] ?? 'Sincronizacion completada.'))
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Error en sincronizacion de oportunidades')
                    ->body((string) ($result['message'] ?? 'Error durante la sincronizacion.'))
                    ->danger()
                    ->send();
            }

            $this->loadStats();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->dispatch('sync-completed');
        }
    }
}
