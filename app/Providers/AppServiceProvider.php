<?php

namespace App\Providers;

use App\Models\Payment;
use App\Models\PlantReservation;
use App\Observers\CommandRunObserver;
use App\Observers\PaymentObserver;
use App\Observers\PersonalAccessTokenObserver;
use App\Observers\PlantReservationObserver;
use App\Services\FinMail\FinMailNotificationService;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\PlantReservationService;
use Awcodes\Curator\Facades\Curator;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar Payment Gateway Manager como singleton
        $this->app->singleton('payment.gateway', function ($app) {
            return new PaymentGatewayManager;
        });

        // Alias para facilitar uso
        $this->app->alias('payment.gateway', PaymentGatewayManager::class);

        // Registrar Plant Reservation Service como singleton
        $this->app->singleton(PlantReservationService::class);

        // Servicio para notificaciones de correo con Fin Mail
        $this->app->singleton(FinMailNotificationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Curator::configure()
            ->acceptedFileTypes([
                'image/*',
                'image/gif',
                'video/*',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/xml',
                'text/xml',
                'text/plain',
                'text/csv',
            ])
            ->maxSize(512000) // KB (~500 MB) para permitir videos
            ->disk((string) config('curator.default_disk', 'curator'))
            ->visibility('public');

        Payment::observe(PaymentObserver::class);
        PlantReservation::observe(PlantReservationObserver::class);

        $sanctumTokenModel = Sanctum::personalAccessTokenModel();

        $sanctumTokenModel::observe(PersonalAccessTokenObserver::class);

        if (class_exists(\BinaryBuilds\CommandRunner\Models\CommandRun::class)) {
            \BinaryBuilds\CommandRunner\Models\CommandRun::observe(CommandRunObserver::class);
        }
    }
}
