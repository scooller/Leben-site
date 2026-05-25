<?php

namespace App\Filament\Widgets;

use App\Models\ContactSubmission;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Schema;

class ContactSubmissionsByChannelChartWidget extends ChartWidget
{
    protected ?string $heading = 'Contactos por Canal';

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        if (! Schema::hasTable('contact_submissions')) {
            return $this->emptyData();
        }

        try {
            $rows = ContactSubmission::query()
                ->selectRaw('contact_channel_id, COUNT(*) as total')
                ->with('channel:id,name')
                ->groupBy('contact_channel_id')
                ->orderByDesc('total')
                ->get();
        } catch (\Throwable) {
            return $this->emptyData();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Contactos',
                    'data' => $rows->pluck('total')->map(fn($total): int => (int) $total)->toArray(),
                    'backgroundColor' => [
                        'rgba(235, 0, 41, 0.85)',
                        'rgba(59, 130, 246, 0.85)',
                        'rgba(16, 185, 129, 0.85)',
                        'rgba(245, 158, 11, 0.85)',
                        'rgba(139, 92, 246, 0.85)',
                    ],
                ],
            ],
            'labels' => $rows->map(fn(ContactSubmission $submission): string => $submission->channel?->name ?? 'Sin canal')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
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
