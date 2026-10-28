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
            $table->unsignedBigInteger('map_logo_id')->nullable()->after('map_description');
            $table->unsignedBigInteger('map_pin_logo_id')->nullable()->after('map_pin_y');

            $table->foreign('map_logo_id')
                ->references('id')
                ->on('curator')
                ->onDelete('set null');

            $table->foreign('map_pin_logo_id')
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
            $table->dropForeign(['map_logo_id']);
            $table->dropForeign(['map_pin_logo_id']);
            $table->dropColumn(['map_logo_id', 'map_pin_logo_id']);
        });
    }
};
