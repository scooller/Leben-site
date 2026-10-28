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
            $table->unsignedBigInteger('equipment_bg_image_id')->nullable()->after('equipment_description');
            $table->unsignedBigInteger('espacios_bg_image_id')->nullable()->after('espacios_description');
            $table->unsignedBigInteger('interiorismo_bg_image_id')->nullable()->after('interiorismo_description');

            $table->foreign('equipment_bg_image_id')
                ->references('id')
                ->on('curator')
                ->onDelete('set null');

            $table->foreign('espacios_bg_image_id')
                ->references('id')
                ->on('curator')
                ->onDelete('set null');

            $table->foreign('interiorismo_bg_image_id')
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
            $table->dropForeign(['equipment_bg_image_id']);
            $table->dropForeign(['espacios_bg_image_id']);
            $table->dropForeign(['interiorismo_bg_image_id']);
            $table->dropColumn([
                'equipment_bg_image_id',
                'espacios_bg_image_id',
                'interiorismo_bg_image_id',
            ]);
        });
    }
};
