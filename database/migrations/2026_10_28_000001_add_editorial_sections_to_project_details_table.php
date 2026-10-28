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
            // Sección Proyecto
            $table->string('project_eyebrow', 150)->nullable()->after('technical_info');
            $table->string('project_title')->nullable()->after('project_eyebrow');
            $table->text('project_description')->nullable()->after('project_title');
            $table->string('project_highlight_offer')->nullable()->after('project_description');

            // Banners de Video Intermedios
            $table->json('video_interstitials')->nullable()->after('project_slides');

            // Sección Equipamiento / Departamentos
            $table->string('equipment_eyebrow', 150)->nullable()->after('video_interstitials');
            $table->string('equipment_title')->nullable()->after('equipment_eyebrow');
            $table->text('equipment_description')->nullable()->after('equipment_title');
            $table->string('equipment_bg_image_url', 500)->nullable()->after('equipment_description');
            $table->json('equipment_spaces_modal')->nullable()->after('equipment_slides');

            // Recorridos 360 & Masterplan
            $table->json('virtual_tours_config')->nullable()->after('virtual_tour_video_url');

            // Sección Espacios Comunes
            $table->string('espacios_eyebrow', 150)->nullable()->after('virtual_tours_config');
            $table->string('espacios_title')->nullable()->after('espacios_eyebrow');
            $table->text('espacios_description')->nullable()->after('espacios_title');
            $table->string('espacios_bg_image_url', 500)->nullable()->after('espacios_description');
            $table->json('espacios_items')->nullable()->after('espacios_bg_image_url');

            // Sección Mapa & Ubicación
            $table->string('map_eyebrow', 150)->nullable()->after('panoramas');
            $table->string('map_title')->nullable()->after('map_eyebrow');
            $table->text('map_description')->nullable()->after('map_title');
            $table->string('map_logo_url', 500)->nullable()->after('map_description');
            $table->string('map_btn_text', 100)->nullable()->after('map_logo_url');
            $table->string('map_address', 255)->nullable()->after('map_btn_text');
            $table->decimal('map_pin_x', 5, 2)->nullable()->after('map_address');
            $table->decimal('map_pin_y', 5, 2)->nullable()->after('map_pin_x');
            $table->string('map_pin_logo_url', 500)->nullable()->after('map_pin_y');
            $table->json('map_pois')->nullable()->after('map_pin_logo_url');

            // Sección Interiorismo
            $table->string('interiorismo_eyebrow', 150)->nullable()->after('map_pois');
            $table->string('interiorismo_title')->nullable()->after('interiorismo_eyebrow');
            $table->text('interiorismo_description')->nullable()->after('interiorismo_title');
            $table->string('interiorismo_bg_image_url', 500)->nullable()->after('interiorismo_description');

            // Modal Panoramas / Experiencias
            $table->json('panoramas_categories')->nullable()->after('interiorismo_bg_image_url');

            // Sección Contacto & Sala de Ventas
            $table->string('contact_eyebrow', 150)->nullable()->after('panoramas_categories');
            $table->string('contact_title')->nullable()->after('contact_eyebrow');
            $table->text('contact_subtitle')->nullable()->after('contact_title');
            $table->string('contact_waze_url', 500)->nullable()->after('contact_subtitle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_details', function (Blueprint $table): void {
            $table->dropColumn([
                'project_eyebrow',
                'project_title',
                'project_description',
                'project_highlight_offer',
                'video_interstitials',
                'equipment_eyebrow',
                'equipment_title',
                'equipment_description',
                'equipment_bg_image_url',
                'equipment_spaces_modal',
                'virtual_tours_config',
                'espacios_eyebrow',
                'espacios_title',
                'espacios_description',
                'espacios_bg_image_url',
                'espacios_items',
                'map_eyebrow',
                'map_title',
                'map_description',
                'map_logo_url',
                'map_btn_text',
                'map_address',
                'map_pin_x',
                'map_pin_y',
                'map_pin_logo_url',
                'map_pois',
                'interiorismo_eyebrow',
                'interiorismo_title',
                'interiorismo_description',
                'interiorismo_bg_image_url',
                'panoramas_categories',
                'contact_eyebrow',
                'contact_title',
                'contact_subtitle',
                'contact_waze_url',
            ]);
        });
    }
};
