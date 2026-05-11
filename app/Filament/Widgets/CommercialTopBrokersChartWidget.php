<?php

namespace App\Filament\Widgets;

use App\Models\SalesforceOpportunity;
use Filament\Widgets\ChartWidget;

class CommercialTopBrokersChartWidget extends ChartWidget
{
    protected ?string $heading = 'Top brokers por oportunidades (30d)';

    protected ?string $pollingInterval = '300s';

    protected int|string|array $columnSpan = [
        'md' => 2,
    ];

    protected function getData(): array
    {
        $rows = SalesforceOpportunity::query()
            ->where('is_deleted', false)
            ->where('is_private', false)
            ->where('salesforce_created_at', '>=', now()->subDays(30))
            ->selectRaw("COALESCE(NULLIF(broker_name, ''), 'Sin broker') as broker_label")
            ->selectRaw('COUNT(*) as opportunities')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->groupByRaw("COALESCE(NULLIF(broker_name, ''), 'Sin broker')")
            ->orderByDesc('opportunities')
            ->limit(8)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Oportunidades',
                    'data' => $rows->pluck('opportunities')->map(fn ($value) => (int) $value)->toArray(),
                    'backgroundColor' => '#2563eb',
                    'borderColor' => '#1d4ed8',
                ],
            ],
            'labels' => $rows->pluck('broker_label')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
