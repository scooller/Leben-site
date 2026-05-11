<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class FinancialTopProjectsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Top proyectos por pagos completados (30d)';

    protected ?string $pollingInterval = '300s';

    protected int|string|array $columnSpan = [
        'md' => 2,
    ];

    protected function getData(): array
    {
        $rows = DB::table('payments')
            ->join('proyectos', 'proyectos.id', '=', 'payments.project_id')
            ->whereIn('payments.status', [
                PaymentStatus::COMPLETED->value,
                PaymentStatus::AUTHORIZED->value,
            ])
            ->whereNotNull('payments.completed_at')
            ->where('payments.completed_at', '>=', now()->subDays(30))
            ->selectRaw('proyectos.name as project_name')
            ->selectRaw('COUNT(payments.id) as sales_count')
            ->selectRaw('COALESCE(SUM(payments.amount), 0) as total_amount')
            ->groupBy('proyectos.name')
            ->orderByDesc('sales_count')
            ->limit(8)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Ventas pagadas',
                    'data' => $rows->pluck('sales_count')->map(fn ($value) => (int) $value)->toArray(),
                    'backgroundColor' => '#059669',
                    'borderColor' => '#047857',
                ],
            ],
            'labels' => $rows->pluck('project_name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
