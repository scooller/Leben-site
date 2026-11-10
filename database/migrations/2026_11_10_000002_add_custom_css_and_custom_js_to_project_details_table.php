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
        Schema::table('project_details', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_details', 'custom_css')) {
                $table->longText('custom_css')->nullable()->after('announcement');
            }
            if (! Schema::hasColumn('project_details', 'custom_js')) {
                $table->longText('custom_js')->nullable()->after('custom_css');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_details', function (Blueprint $table): void {
            if (Schema::hasColumn('project_details', 'custom_js')) {
                $table->dropColumn('custom_js');
            }
            if (Schema::hasColumn('project_details', 'custom_css')) {
                $table->dropColumn('custom_css');
            }
        });
    }
};

