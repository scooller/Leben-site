<?php

namespace App\Filament\Widgets;

use App\Models\PersonalAccessToken;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ApiUsageChartWidget extends ChartWidget
{
    protected ?string $heading = 'Uso API (Últimos 7 días)';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return [
                'datasets' => [
                    [
                        'label' => 'Uso API',
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        $days = $this->lastSevenDays();

        $data = $days->map(function (array $day): int {
            return PersonalAccessToken::query()
                ->whereNotNull('last_used_at')
                ->whereDate('last_used_at', $day['date'])
                ->count();
        });

        return [
            'datasets' => [
                [
                    'label' => 'Uso API',
                    'data' => $data->toArray(),
                    'borderColor' => '#0ea5e9',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.15)',
                ],
            ],
            'labels' => $days->pluck('label')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return Collection<int, array{date: string, label: string}>
     */
    private function lastSevenDays(): Collection
    {
        $days = collect();

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $days->push([
                'date' => $date->toDateString(),
                'label' => $date->translatedFormat('d M'),
            ]);
        }

        return $days;
    }
}
