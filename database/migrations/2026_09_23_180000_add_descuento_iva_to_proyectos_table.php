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
            if (! Schema::hasColumn('proyectos', 'descuento_iva')) {
                $table->decimal('descuento_iva', 8, 2)
                    ->default(0)
                    ->after('descuento_maximo_unidad');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            if (Schema::hasColumn('proyectos', 'descuento_iva')) {
                $table->dropColumn('descuento_iva');
            }
        });
    }
};
