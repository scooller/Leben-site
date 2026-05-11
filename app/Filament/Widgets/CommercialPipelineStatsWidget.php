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
            ->where('is_deleted', false)
            ->where('is_private', false)
            ->where('salesforce_created_at', '>=', now()->subDays(30));

        $total = (clone $baseQuery)->count();
        $open = (clone $baseQuery)->where('is_closed', false)->count();
        $won = (clone $baseQuery)->where('is_won', true)->count();
        $closureRate = $total > 0 ? round(($won / $total) * 100, 2) : 0.0;

        $topBroker = (clone $baseQuery)
            ->selectRaw("COALESCE(NULLIF(broker_name, ''), 'Sin broker') as broker_label, COUNT(*) as opportunities")
            ->groupByRaw("COALESCE(NULLIF(broker_name, ''), 'Sin broker')")
            ->orderByDesc('opportunities')
            ->first();

        $topProject = (clone $baseQuery)
            ->selectRaw("COALESCE(NULLIF(proyecto_name, ''), 'Sin proyecto') as project_label, COUNT(*) as opportunities")
            ->groupByRaw("COALESCE(NULLIF(proyecto_name, ''), 'Sin proyecto')")
            ->orderByDesc('opportunities')
            ->first();

        return [
            Stat::make('Oportunidades (30d)', (string) $total)
                ->description((string) $open.' abiertas')
                ->color('info'),
            Stat::make('Tasa cierre (30d)', number_format($closureRate, 2).'%')
                ->description((string) $won.' ganadas')
                ->color($closureRate > 0 ? 'success' : 'gray'),
            Stat::make('Top broker (30d)', (string) ($topBroker->broker_label ?? '-'))
                ->description((string) ($topBroker->opportunities ?? 0).' oportunidades')
                ->color('primary'),
            Stat::make('Top proyecto (30d)', (string) ($topProject->project_label ?? '-'))
                ->description((string) ($topProject->opportunities ?? 0).' oportunidades')
                ->color('warning'),
        ];
    }
}
