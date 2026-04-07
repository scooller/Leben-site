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
        Payment::observe(PaymentObserver::class);
        PlantReservation::observe(PlantReservationObserver::class);

        $sanctumTokenModel = Sanctum::personalAccessTokenModel();

        $sanctumTokenModel::observe(PersonalAccessTokenObserver::class);

        if (class_exists(\BinaryBuilds\CommandRunner\Models\CommandRun::class)) {
            \BinaryBuilds\CommandRunner\Models\CommandRun::observe(CommandRunObserver::class);
        }
    }
}
