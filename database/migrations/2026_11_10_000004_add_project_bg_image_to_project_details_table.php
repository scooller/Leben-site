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
            if (! Schema::hasColumn('project_details', 'project_bg_image_url')) {
                $table->string('project_bg_image_url', 500)->nullable()->after('project_description');
            }

            if (! Schema::hasColumn('project_details', 'project_bg_image_id')) {
                $table->unsignedBigInteger('project_bg_image_id')->nullable()->after('project_bg_image_url');
                $table->foreign('project_bg_image_id')
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
            if (Schema::hasColumn('project_details', 'project_bg_image_id')) {
                $table->dropForeign(['project_bg_image_id']);
                $table->dropColumn(['project_bg_image_id']);
            }

            if (Schema::hasColumn('project_details', 'project_bg_image_url')) {
                $table->dropColumn(['project_bg_image_url']);
            }
        });
    }
};

