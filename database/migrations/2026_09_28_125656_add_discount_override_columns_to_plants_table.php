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
        Schema::table('plants', function (Blueprint $table) {
            $table->boolean('priorizar_descuentos')->default(false)->after('unidad_sale');
            $table->decimal('descuento_maximo_unidad', 8, 2)->nullable()->after('descuento_defecto_cotizacion_web');
            $table->decimal('descuento_iva', 8, 2)->nullable()->default(0)->after('descuento_maximo_unidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->dropColumn([
                'priorizar_descuentos',
                'descuento_maximo_unidad',
                'descuento_iva',
            ]);
        });
    }
};
