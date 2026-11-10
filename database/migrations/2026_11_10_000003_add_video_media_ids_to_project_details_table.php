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
            if (! Schema::hasColumn('project_details', 'hero_video_media_id')) {
                $table->unsignedBigInteger('hero_video_media_id')->nullable()->after('hero_video_url');
                $table->foreign('hero_video_media_id')
                    ->references('id')
                    ->on('curator')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('project_details', 'virtual_tour_360_media_id')) {
                $table->unsignedBigInteger('virtual_tour_360_media_id')->nullable()->after('virtual_tour_360_url');
                $table->foreign('virtual_tour_360_media_id')
                    ->references('id')
                    ->on('curator')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('project_details', 'virtual_tour_video_media_id')) {
                $table->unsignedBigInteger('virtual_tour_video_media_id')->nullable()->after('virtual_tour_video_url');
                $table->foreign('virtual_tour_video_media_id')
                    ->references('id')
                    ->on('curator')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_details', function (Blueprint $table): void {
            if (Schema::hasColumn('project_details', 'hero_video_media_id')) {
                $table->dropForeign(['hero_video_media_id']);
                $table->dropColumn(['hero_video_media_id']);
            }

            if (Schema::hasColumn('project_details', 'virtual_tour_360_media_id')) {
                $table->dropForeign(['virtual_tour_360_media_id']);
                $table->dropColumn(['virtual_tour_360_media_id']);
            }

            if (Schema::hasColumn('project_details', 'virtual_tour_video_media_id')) {
                $table->dropForeign(['virtual_tour_video_media_id']);
                $table->dropColumn(['virtual_tour_video_media_id']);
            }
        });
    }
};

