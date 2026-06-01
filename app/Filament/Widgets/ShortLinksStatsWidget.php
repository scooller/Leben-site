<?php

namespace App\Filament\Widgets;

use App\Models\ShortLink;
use Filament\Widgets\Widget;
use Throwable;

class ShortLinksStatsWidget extends Widget
{
    protected string $view = 'filament.widgets.short-links-stats-widget';

    protected int|string|array $columnSpan = [
        'md' => 2,
    ];

    public int $totalLinks = 0;

    public int $activeLinks = 0;

    public int $totalVisits = 0;

    public int $visitsLast7Days = 0;

    public ?string $resourceUrl = null;

    public function mount(): void
    {
        $this->loadStats();
        try {
            $this->resourceUrl = route('filament.admin.resources.short-links.index');
        } catch (Throwable) {
            $this->resourceUrl = '#';
        }
    }

    public function loadStats(): void
    {
        $this->totalLinks = ShortLink::count();
        $this->activeLinks = ShortLink::active()->count();
        $this->totalVisits = ShortLink::sum('visits_count') ?? 0;

        // Visitas en los últimos 7 días
        $this->visitsLast7Days = ShortLink::query()
            ->with('visits')
            ->get()
            ->sum(function ($link) {
                return $link->visits()
                    ->where('visited_at', '>=', now()->subDays(7))
                    ->count();
            });
    }
}
