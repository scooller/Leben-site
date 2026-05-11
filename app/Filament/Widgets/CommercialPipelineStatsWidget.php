<?php

namespace App\Filament\Widgets;

use App\Models\SalesforceOpportunity;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CommercialPipelineStatsWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Pipeline comercial (Salesforce)';

    protected ?string $pollingInterval = '300s';

    protected function getStats(): array
    {
        $baseQuery = SalesforceOpportunity::query()
            ->where(function ($query): void {
                $query->where('is_deleted', false)
                    ->orWhereNull('is_deleted');
            })
            ->where(function ($query): void {
                $query->where('is_private', false)
                    ->orWhereNull('is_private');
            })
            ->where('salesforce_created_at', '>=', now()->subDays(30));

        $total = (clone $baseQuery)->count();
        $open = (clone $baseQuery)->where('is_closed', false)->count();
        $won = (clone $baseQuery)->where('is_won', true)->count();
        $withAssignedBroker = (clone $baseQuery)
            ->where(function ($query): void {
                $query->whereNotNull('broker_salesforce_id')
                    ->where('broker_salesforce_id', '!=', '')
                    ->orWhere(function ($inner): void {
                        $inner->whereNotNull('broker_name')
                            ->whereRaw("TRIM(broker_name) != ''");
                    });
            })
            ->count();
        $withoutAssignedBroker = max($total - $withAssignedBroker, 0);
        $brokerCoverage = $total > 0 ? round(($withAssignedBroker / $total) * 100, 2) : 0.0;
        $closureRate = $total > 0 ? round(($won / $total) * 100, 2) : 0.0;

        $topBroker = (clone $baseQuery)
            ->whereNotNull('broker_name')
            ->whereRaw("TRIM(broker_name) != ''")
            ->selectRaw('broker_name, COUNT(*) as opportunities')
            ->groupBy('broker_name')
            ->orderByDesc('opportunities')
            ->first();

        $topProject = (clone $baseQuery)
            ->selectRaw('proyecto_name, COUNT(*) as opportunities')
            ->groupBy('proyecto_name')
            ->orderByDesc('opportunities')
            ->first();

        $topBrokerLabel = trim((string) ($topBroker->broker_name ?? ''));
        $topProjectLabel = trim((string) ($topProject->proyecto_name ?? ''));

        return [
            Stat::make('Oportunidades (30d)', (string) $total)
                ->description((string) $open.' abiertas')
                ->color('info'),
            Stat::make('Tasa cierre (30d)', number_format($closureRate, 2).'%')
                ->description((string) $won.' ganadas')
                ->color($closureRate > 0 ? 'success' : 'gray'),
            Stat::make('Sin broker asignado (30d)', (string) $withoutAssignedBroker)
                ->description(number_format($brokerCoverage, 2).'% con broker')
                ->color($withoutAssignedBroker > 0 ? 'danger' : 'success'),
            Stat::make('Cobertura broker (30d)', number_format($brokerCoverage, 2).'%')
                ->description((string) $withAssignedBroker.' de '.(string) $total.' oportunidades')
                ->color($brokerCoverage >= 80 ? 'success' : ($brokerCoverage >= 50 ? 'warning' : 'danger')),
            Stat::make('Top broker atribuido (30d)', $topBrokerLabel !== '' ? $topBrokerLabel : '-')
                ->description((string) ($topBroker->opportunities ?? 0).' oportunidades')
                ->color('primary'),
            Stat::make('Top proyecto (30d)', $topProjectLabel !== '' ? $topProjectLabel : 'Sin proyecto')
                ->description((string) ($topProject->opportunities ?? 0).' oportunidades')
                ->color('warning'),
        ];
    }
}
