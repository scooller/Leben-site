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
            $table->unsignedBigInteger('panoramas_bg_image_id')->nullable()->after('panoramas_categories');
            $table->string('panoramas_bg_image_url', 500)->nullable()->after('panoramas_bg_image_id');

            $table->foreign('panoramas_bg_image_id')
                ->references('id')
                ->on('curator')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_details', function (Blueprint $table): void {
            $table->dropForeign(['panoramas_bg_image_id']);
            $table->dropColumn(['panoramas_bg_image_id', 'panoramas_bg_image_url']);
        });
    }
};

