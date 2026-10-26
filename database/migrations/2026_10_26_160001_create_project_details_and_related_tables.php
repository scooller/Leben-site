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
        Schema::dropIfExists('project_galleries');
        Schema::dropIfExists('project_pois');
        Schema::dropIfExists('project_amenities');
        Schema::dropIfExists('project_details');

        Schema::create('project_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained('proyectos')->cascadeOnDelete();
            $table->string('hero_eyebrow', 150)->nullable();
            $table->string('hero_title')->nullable();
            $table->string('hero_subtitle')->nullable();
            $table->string('hero_video_url', 500)->nullable();
            $table->foreignId('hero_logo_id')->nullable()->constrained('curator')->nullOnDelete();
            $table->string('hero_logo_url', 500)->nullable();
            $table->json('theme_colors')->nullable();
            $table->json('technical_info')->nullable();
            $table->json('project_slides')->nullable();
            $table->json('equipment_brands')->nullable();
            $table->json('equipment_slides')->nullable();
            $table->string('overview_title')->nullable();
            $table->text('overview_description')->nullable();
            $table->json('overview_images')->nullable();
            $table->json('overview_stats')->nullable();
            $table->string('terminaciones_title')->nullable();
            $table->text('terminaciones_description')->nullable();
            $table->json('terminaciones_items')->nullable();
            $table->string('virtual_tour_matterport_url', 500)->nullable();
            $table->string('virtual_tour_360_url', 500)->nullable();
            $table->string('virtual_tour_video_url', 500)->nullable();
            $table->string('brochure_pdf_url', 500)->nullable();
            $table->json('designers')->nullable();
            $table->json('panoramas')->nullable();
            $table->text('entorno_description')->nullable();
            $table->foreignId('entorno_map_image_id')->nullable()->constrained('curator')->nullOnDelete();
            $table->string('entorno_map_image_url', 500)->nullable();
            $table->json('schedule')->nullable();
            $table->json('announcement')->nullable();
            $table->timestamps();
        });

        Schema::create('project_amenities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_detail_id')->constrained('project_details')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('icon_key', 50)->default('bell');
            $table->foreignId('image_id')->nullable()->constrained('curator')->nullOnDelete();
            $table->string('image_url', 500)->nullable();
            $table->string('link_url', 500)->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('project_pois', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_detail_id')->constrained('project_details')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('category', 100);
            $table->string('distance', 50);
            $table->string('walking_time', 50);
            $table->foreignId('image_id')->nullable()->constrained('curator')->nullOnDelete();
            $table->string('image_url', 500)->nullable();
            $table->decimal('coordinate_x', 5, 2);
            $table->decimal('coordinate_y', 5, 2);
            $table->string('pin_direction', 20)->default('right');
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('project_galleries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_detail_id')->constrained('project_details')->cascadeOnDelete();
            $table->string('category', 50)->index();
            $table->string('title', 150)->nullable();
            $table->foreignId('image_id')->nullable()->constrained('curator')->nullOnDelete();
            $table->string('image_url', 500)->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('alt_text')->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_galleries');
        Schema::dropIfExists('project_pois');
        Schema::dropIfExists('project_amenities');
        Schema::dropIfExists('project_details');
    }
};
