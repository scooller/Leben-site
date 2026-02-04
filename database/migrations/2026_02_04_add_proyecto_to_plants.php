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
            // Agregar columna para vincular con proyectos
            $table->string('salesforce_proyecto_id')->nullable()->after('salesforce_product_id');
            $table->index('salesforce_proyecto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->dropIndex(['salesforce_proyecto_id']);
            $table->dropColumn('salesforce_proyecto_id');
        });
    }
};
