<?php

namespace App\Filament\Widgets;

use App\Models\ContactSubmission;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContactSubmissionsStatsWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Contactos';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $totalContacts = ContactSubmission::query()->count();
        $contactsToday = ContactSubmission::query()
            ->whereDate('created_at', now()->toDateString())
            ->count();
        $contactsLast7Days = ContactSubmission::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $syncedWithSalesforce = ContactSubmission::query()
            ->whereNotNull('salesforce_case_id')
            ->count();

        return [
            Stat::make('Total contactos', (string) $totalContacts)
                ->description('Registros acumulados')
                ->color('primary'),
            Stat::make('Contactos hoy', (string) $contactsToday)
                ->description('Ingresados hoy')
                ->color('emerald'),
            Stat::make('Últimos 7 días', (string) $contactsLast7Days)
                ->description('Actividad reciente')
                ->color('info'),
            Stat::make('Sincronizados SF', (string) $syncedWithSalesforce)
                ->description('Con Lead ID en Salesforce')
                ->color('warning'),
        ];
    }
}
