<?php

namespace App\Filament\Widgets;

use App\Models\PersonalAccessToken;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class ApiMonitoringWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Monitoreo API';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return [
                Stat::make('Tokens API activos', '0')
                    ->description('Tabla de tokens no disponible')
                    ->color('gray'),
            ];
        }

        $totalTokens = PersonalAccessToken::query()->count();
        $activeTokens = PersonalAccessToken::query()->active()->count();
        $usedLast24Hours = PersonalAccessToken::query()
            ->whereNotNull('last_used_at')
            ->where('last_used_at', '>=', now()->subDay())
            ->count();
        $expiringIn7Days = PersonalAccessToken::query()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(7)])
            ->count();

        return [
            Stat::make('Tokens API activos', (string) $activeTokens)
                ->description("{$totalTokens} tokens totales")
                ->color('emerald'),
            Stat::make('Uso API (24h)', (string) $usedLast24Hours)
                ->description('Tokens con actividad en últimas 24 horas')
                ->color('blue'),
            Stat::make('Expiran en 7 días', (string) $expiringIn7Days)
                ->description('Tokens próximos a expirar')
                ->color($expiringIn7Days > 0 ? 'amber' : 'gray'),
        ];
    }
}
