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
            if (! Schema::hasColumn('site_settings', 'show_theme_toggle')) {
                $table->boolean('show_theme_toggle')->default(true)->after('webawesome_palette');
            }

            if (! Schema::hasColumn('site_settings', 'default_color_mode')) {
                $table->string('default_color_mode', 20)->default('system')->after('show_theme_toggle');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (Schema::hasColumn('site_settings', 'show_theme_toggle')) {
                $table->dropColumn('show_theme_toggle');
            }

            if (Schema::hasColumn('site_settings', 'default_color_mode')) {
                $table->dropColumn('default_color_mode');
            }
        });
    }
};
