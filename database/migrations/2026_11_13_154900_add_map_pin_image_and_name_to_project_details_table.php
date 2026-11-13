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
            $table->unsignedBigInteger('map_pin_image_id')->nullable()->after('map_pin_logo_url');
            $table->string('map_pin_image_url', 500)->nullable()->after('map_pin_image_id');
            $table->string('map_pin_name', 150)->nullable()->after('map_pin_image_url');

            $table->foreign('map_pin_image_id')
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
            $table->dropForeign(['map_pin_image_id']);
            $table->dropColumn(['map_pin_image_id', 'map_pin_image_url', 'map_pin_name']);
        });
    }
};

