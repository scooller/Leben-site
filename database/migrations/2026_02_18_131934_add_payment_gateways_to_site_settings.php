<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            // Configuración de pasarelas de pago
            $table->boolean('gateway_transbank_enabled')->default(true)->after('maintenance_message');
            $table->boolean('gateway_mercadopago_enabled')->default(false)->after('gateway_transbank_enabled');
            $table->boolean('gateway_manual_enabled')->default(true)->after('gateway_mercadopago_enabled');

            // Configuración específica por pasarela
            $table->json('gateway_transbank_config')->nullable()->after('gateway_manual_enabled');
            $table->json('gateway_mercadopago_config')->nullable()->after('gateway_transbank_config');
            $table->json('gateway_manual_config')->nullable()->after('gateway_mercadopago_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'gateway_transbank_enabled',
                'gateway_mercadopago_enabled',
                'gateway_manual_enabled',
                'gateway_transbank_config',
                'gateway_mercadopago_config',
                'gateway_manual_config',
            ]);
        });
    }
};
