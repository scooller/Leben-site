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
            if (! Schema::hasColumn('proyectos', 'salesforce_logo_url')) {
                $table->text('salesforce_logo_url')
                    ->nullable()
                    ->after('project_image_id');
            }

            if (! Schema::hasColumn('proyectos', 'salesforce_portada_url')) {
                $table->text('salesforce_portada_url')
                    ->nullable()
                    ->after('salesforce_logo_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            if (Schema::hasColumn('proyectos', 'salesforce_portada_url')) {
                $table->dropColumn('salesforce_portada_url');
            }

            if (Schema::hasColumn('proyectos', 'salesforce_logo_url')) {
                $table->dropColumn('salesforce_logo_url');
            }
        });
    }
};
