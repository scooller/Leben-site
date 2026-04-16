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
        Schema::table('frontend_preview_links', function (Blueprint $table) {
            $table->string('preview_path')->default('/')->after('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('frontend_preview_links', function (Blueprint $table) {
            $table->dropColumn('preview_path');
        });
    }
};
