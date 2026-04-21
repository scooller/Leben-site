<?php

namespace App\Providers\Filament;

use AchyutN\FilamentLogViewer\FilamentLogViewer;
use AlizHarb\ActivityLog\ActivityLogPlugin;
use AlizHarb\ActivityLog\Widgets\ActivityChartWidget;
use AlizHarb\ActivityLog\Widgets\LatestActivityWidget;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ApiMonitoringWidget;
use App\Filament\Widgets\ApiUsageChartWidget;
use App\Filament\Widgets\PaymentGatewayChartWidget;
use App\Filament\Widgets\PaymentsChartWidget;
use App\Filament\Widgets\PaymentStatusChartWidget;
use App\Filament\Widgets\ShortLinksStatsWidget;
use App\Filament\Widgets\ShortLinksVisitsChartWidget;
use App\Filament\Widgets\SyncPlantsWidget;
use App\Filament\Widgets\SyncProjectsWidget;
use App\Filament\Widgets\UsersChartWidget;
use App\Http\Middleware\EnsureMarketingPanelAccess;
use App\Models\SiteSetting;
use BinaryBuilds\CommandRunner\CommandRunnerPlugin;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use FinityLabs\FinMail\FinMailPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Throwable;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $settings = $this->resolveSiteSettings();

        $defaultWidgets = [
            AccountWidget::class,
            ApiMonitoringWidget::class,
            ApiUsageChartWidget::class,
            UsersChartWidget::class,
            ActivityChartWidget::class,
            LatestActivityWidget::class,
            PaymentsChartWidget::class,
            PaymentGatewayChartWidget::class,
            PaymentStatusChartWidget::class,
            ShortLinksStatsWidget::class,
            ShortLinksVisitsChartWidget::class,
            SyncPlantsWidget::class,
            SyncProjectsWidget::class,
        ];

        $widgetOrder = $settings?->dashboard_widget_order;
        $widgetOrder = is_array($widgetOrder) ? array_values($widgetOrder) : [];

        if (! empty($widgetOrder)) {
            $ordered = array_values(array_filter($widgetOrder, fn (string $widget): bool => in_array($widget, $defaultWidgets, true)));
            $missing = array_values(array_diff($defaultWidgets, $ordered));
            $widgets = array_merge($ordered, $missing);
        } else {
            $widgets = $defaultWidgets;
        }

        $fontStylesheetUrl = filled($settings?->google_fonts_stylesheet)
            ? (string) $settings->google_fonts_stylesheet
            : null;

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->databaseNotifications()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->font($settings?->font_family_body, $fontStylesheetUrl, LocalFontProvider::class)
            ->serifFont($settings?->font_family_heading, $fontStylesheetUrl, LocalFontProvider::class)
            ->login()
            ->brandName($settings?->site_name ?? 'iLeben')
            ->favicon($settings?->faviconMedia?->url)
            ->brandLogo($settings?->logoMedia?->url)
            ->darkModeBrandLogo($settings?->logoDarkMedia?->url ?? $settings?->logoMedia?->url)
            ->brandLogoHeight('2.5rem')
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): \Illuminate\Contracts\View\View => view('filament.components.view-web-button'),
            )
            ->colors([
                'primary' => '#eb0029',
                'danger' => '#eb0029',
                'warning' => '#eb0029',
                'gray' => '#343a40',
                'info' => '#000000',
                'success' => '#000000',
                'red' => Color::Red,
                'orange' => Color::Orange,
                'amber' => Color::Amber,
                'yellow' => Color::Yellow,
                'lime' => Color::Lime,
                'green' => Color::Green,
                'emerald' => Color::Emerald,
                'teal' => Color::Teal,
                'cyan' => Color::Cyan,
                'sky' => Color::Sky,
                'blue' => Color::Blue,
                'indigo' => Color::Indigo,
                'violet' => Color::Violet,
                'purple' => Color::Purple,
                'fuchsia' => Color::Fuchsia,
                'pink' => Color::Pink,
                'rose' => Color::Rose,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets($widgets)
            ->plugins([
                \Awcodes\Curator\CuratorPlugin::make()
                    ->label('Archivos')
                    ->pluralLabel('Archivos')
                    ->navigationIcon('heroicon-o-photo')
                    ->navigationGroup('Contenido')
                    ->navigationSort(1),
                ActivityLogPlugin::make()
                    ->label('Logs')
                    ->pluralLabel('Logs')
                    ->navigationGroup('Monitoreo')
                    ->navigationSort(1),
                FilamentLogViewer::make()
                    ->authorize(fn (): bool => Auth::user()?->isAdmin() ?? false)
                    ->navigationGroup('Monitoreo')
                    ->navigationIcon('heroicon-o-document-text')
                    ->navigationLabel('Log Viewer')
                    ->navigationSort(2),
                CommandRunnerPlugin::make()
                    ->authorize(fn (): bool => Auth::user()?->isAdmin() ?? false)
                    ->navigationGroup('Herramientas')
                    ->navigationLabel('Command Runner')
                    ->navigationIcon('heroicon-o-command-line')
                    ->navigationSort(1),
                FinMailPlugin::make(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureMarketingPanelAccess::class,
            ]);
    }

    private function resolveSiteSettings(): ?SiteSetting
    {
        try {
            if (! Schema::hasTable('site_settings')) {
                return null;
            }

            $settings = SiteSetting::current();
            $settings->load(['faviconMedia', 'logoMedia', 'logoDarkMedia']);

            return $settings;
        } catch (Throwable) {
            return null;
        }
    }
}
