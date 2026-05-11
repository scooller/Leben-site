<?php

namespace App\Filament\Widgets;

use App\Models\SalesforceOpportunity;
use Filament\Widgets\ChartWidget;

class CommercialTopWonAmountBrokersChartWidget extends ChartWidget
{
    protected ?string $heading = 'Top brokers por monto ganado (all-time)';

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
            ->where('is_won', true)
            ->whereNotNull('broker_name')
            ->whereRaw("TRIM(broker_name) != ''")
            ->selectRaw('broker_name')
            ->selectRaw('COALESCE(SUM(amount), 0) as won_amount')
            ->groupBy('broker_name')
            ->orderByDesc('won_amount')
            ->limit(8)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Monto ganado',
                    'data' => $rows->pluck('won_amount')->map(fn($value) => (float) $value)->toArray(),
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#059669',
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
