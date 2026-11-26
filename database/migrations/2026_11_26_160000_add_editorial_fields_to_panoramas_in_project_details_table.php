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
            $table->string('panoramas_eyebrow', 150)->nullable()->after('designers');
            $table->string('panoramas_title')->nullable()->after('panoramas_eyebrow');
            $table->text('panoramas_description')->nullable()->after('panoramas_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_details', function (Blueprint $table): void {
            $table->dropColumn(['panoramas_eyebrow', 'panoramas_title', 'panoramas_description']);
        });
    }
};
