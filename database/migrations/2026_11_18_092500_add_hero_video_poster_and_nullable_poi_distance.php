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
            if (! Schema::hasColumn('project_details', 'hero_video_poster_id')) {
                $table->unsignedBigInteger('hero_video_poster_id')->nullable()->after('hero_video_media_id');
                $table->foreign('hero_video_poster_id')
                    ->references('id')
                    ->on('curator')
                    ->onDelete('set null');
            }

            if (! Schema::hasColumn('project_details', 'hero_video_poster_url')) {
                $table->string('hero_video_poster_url', 500)->nullable()->after('hero_video_poster_id');
            }
        });

        if (Schema::hasTable('project_pois') && Schema::hasColumn('project_pois', 'distance')) {
            Schema::table('project_pois', function (Blueprint $table): void {
                $table->string('distance', 50)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('project_pois') && Schema::hasColumn('project_pois', 'distance')) {
            Schema::table('project_pois', function (Blueprint $table): void {
                $table->string('distance', 50)->nullable(false)->change();
            });
        }

        Schema::table('project_details', function (Blueprint $table): void {
            if (Schema::hasColumn('project_details', 'hero_video_poster_id')) {
                $table->dropForeign(['hero_video_poster_id']);
                $table->dropColumn(['hero_video_poster_id']);
            }

            if (Schema::hasColumn('project_details', 'hero_video_poster_url')) {
                $table->dropColumn(['hero_video_poster_url']);
            }
        });
    }
};
