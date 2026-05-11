<?php

namespace App\Filament\Widgets;

use App\Models\SalesforceOpportunity;
use Filament\Widgets\ChartWidget;

class CommercialTopOpenBrokersChartWidget extends ChartWidget
{
    protected ?string $heading = 'Brokers con mas oportunidades abiertas (all-time)';

    protected ?string $pollingInterval = '300s';

    protected int|string|array $columnSpan = [
        'md' => 2,
    ];

    protected function getData(): array
    {
        $rows = SalesforceOpportunity::query()
            ->where(function ($query): void {
                $query->where('is_deleted', false)
                    ->orWhereNull('is_deleted');
            })
            ->where(function ($query): void {
                $query->where('is_private', false)
                    ->orWhereNull('is_private');
            })
            ->where('is_closed', false)
            ->whereNotNull('broker_name')
            ->whereRaw("TRIM(broker_name) != ''")
            ->selectRaw('broker_name')
            ->selectRaw('COUNT(*) as open_opportunities')
            ->groupBy('broker_name')
            ->orderByDesc('open_opportunities')
            ->limit(8)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Opp abiertas',
                    'data' => $rows->pluck('open_opportunities')->map(fn($value) => (int) $value)->toArray(),
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                ],
            ],
            'labels' => $rows->pluck('broker_name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
