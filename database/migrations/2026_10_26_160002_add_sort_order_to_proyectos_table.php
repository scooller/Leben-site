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
            if (! Schema::hasColumn('proyectos', 'sort_order')) {
                $table->integer('sort_order')->default(0)->index()->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table): void {
            if (Schema::hasColumn('proyectos', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};
