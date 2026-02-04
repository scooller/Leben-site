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
            // Remover el índice único de salesforce_pricebook_id
            $table->dropUnique(['salesforce_pricebook_id']);
            // Agregar índice regular (sin UNIQUE)
            $table->index('salesforce_pricebook_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->dropIndex(['salesforce_pricebook_id']);
            $table->unique('salesforce_pricebook_id');
        });
    }
};
