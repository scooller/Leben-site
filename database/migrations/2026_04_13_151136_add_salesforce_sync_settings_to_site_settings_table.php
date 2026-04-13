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
            if (! Schema::hasColumn('site_settings', 'salesforce_sync_interval_minutes')) {
                $table->unsignedInteger('salesforce_sync_interval_minutes')
                    ->default(1440)
                    ->after('dashboard_widget_order');
            }

            if (! Schema::hasColumn('site_settings', 'salesforce_sync_plant_types')) {
                $table->json('salesforce_sync_plant_types')
                    ->nullable()
                    ->after('salesforce_sync_interval_minutes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (Schema::hasColumn('site_settings', 'salesforce_sync_plant_types')) {
                $table->dropColumn('salesforce_sync_plant_types');
            }

            if (Schema::hasColumn('site_settings', 'salesforce_sync_interval_minutes')) {
                $table->dropColumn('salesforce_sync_interval_minutes');
            }
        });
    }
};
