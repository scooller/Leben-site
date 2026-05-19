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
        Schema::table('proyectos', function (Blueprint $table) {
            if (! Schema::hasColumn('proyectos', 'descuento_maximo_unidad')) {
                $table->decimal('descuento_maximo_unidad', 8, 2)
                    ->nullable()
                    ->after('descuento_defecto_cotizacion_web');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            if (Schema::hasColumn('proyectos', 'descuento_maximo_unidad')) {
                $table->dropColumn('descuento_maximo_unidad');
            }
        });
    }
};
