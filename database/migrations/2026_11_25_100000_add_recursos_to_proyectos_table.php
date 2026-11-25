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
            if (! Schema::hasColumn('proyectos', 'recursos')) {
                $table->json('recursos')->nullable()->after('gallery_image_ids');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table): void {
            if (Schema::hasColumn('proyectos', 'recursos')) {
                $table->dropColumn('recursos');
            }
        });
    }
};
