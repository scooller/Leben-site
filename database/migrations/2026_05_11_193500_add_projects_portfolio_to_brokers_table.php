<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brokers', function (Blueprint $table) {
            $table->json('projects_portfolio')->nullable()->after('last_stage_name');
        });
    }

    public function down(): void
    {
        Schema::table('brokers', function (Blueprint $table) {
            $table->dropColumn('projects_portfolio');
        });
    }
};
