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
        Schema::table('proyectos', function (Blueprint $table): void {
            if (! Schema::hasColumn('proyectos', 'gallery_image_ids')) {
                $table->json('gallery_image_ids')->nullable()->after('project_image_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table): void {
            if (Schema::hasColumn('proyectos', 'gallery_image_ids')) {
                $table->dropColumn('gallery_image_ids');
            }
        });
    }
};
