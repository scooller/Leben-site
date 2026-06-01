<?php

namespace App\Filament\Widgets;

use App\Models\ContactSubmission;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ContactSubmissionsTrendChartWidget extends ChartWidget
{
    protected ?string $heading = 'Contactos - Últimos 30 días';

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = [
        'md' => 2,
    ];

    protected function getData(): array
    {
        if (! Schema::hasTable('contact_submissions')) {
            return $this->emptyData();
        }

        $days = collect();

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $days->push([
                'date' => $date->toDateString(),
                'label' => $date->translatedFormat('d M'),
            ]);
        }

        try {
            $data = $days->map(function (array $day): int {
                return ContactSubmission::query()
                    ->whereDate('created_at', $day['date'])
                    ->count();
            });
        } catch (Throwable) {
            return $this->emptyData();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Contactos',
                    'data' => $data->toArray(),
                    'borderColor' => '#eb0029',
                    'backgroundColor' => 'rgba(235, 0, 41, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $days->pluck('label')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function emptyData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Contactos',
                    'data' => [],
                ],
            ],
            'labels' => [],
        ];
    }
}
