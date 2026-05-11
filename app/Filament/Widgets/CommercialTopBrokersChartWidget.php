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
            ->where(function ($query): void {
                $query->where('is_deleted', false)
                    ->orWhereNull('is_deleted');
            })
            ->where(function ($query): void {
                $query->where('is_private', false)
                    ->orWhereNull('is_private');
            })
            ->where('salesforce_created_at', '>=', now()->subDays(30))
            ->selectRaw('broker_name')
            ->selectRaw('COUNT(*) as opportunities')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('broker_name')
            ->orderByDesc('opportunities')
            ->limit(8)
            ->get();

        $labels = $rows
            ->map(function ($row): string {
                $label = trim((string) ($row->broker_name ?? ''));

                return $label !== '' ? $label : 'Sin broker';
            })
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Oportunidades',
                    'data' => $rows->pluck('opportunities')->map(fn($value) => (int) $value)->toArray(),
                    'backgroundColor' => '#2563eb',
                    'borderColor' => '#1d4ed8',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
