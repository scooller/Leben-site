<?php

namespace App\Providers;

use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
