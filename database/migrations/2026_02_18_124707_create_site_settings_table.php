<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();

            // Información básica del sitio
            $table->string('site_name')->default('iLeben');
            $table->text('site_description')->nullable();
            $table->string('site_url')->nullable();

            // Branding - Logos e íconos
            $table->string('logo')->nullable(); // Logo principal
            $table->string('logo_dark')->nullable(); // Logo para modo oscuro
            $table->string('favicon')->nullable();
            $table->string('icon')->nullable(); // Ícono/isotipo

            // Colores del tema
            $table->string('primary_color')->default('#667eea');
            $table->string('secondary_color')->default('#764ba2');
            $table->string('accent_color')->nullable();
            $table->string('background_color')->default('#ffffff');
            $table->string('text_color')->default('#1f2937');

            // SEO
            $table->text('meta_keywords')->nullable();
            $table->string('meta_author')->nullable();
            $table->string('og_image')->nullable(); // Open Graph image

            // Contacto
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('contact_address')->nullable();

            // Redes sociales
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('youtube_url')->nullable();

            // Scripts y estilos personalizados
            $table->text('custom_css')->nullable();
            $table->text('custom_js')->nullable();
            $table->text('header_scripts')->nullable(); // Scripts en <head>
            $table->text('footer_scripts')->nullable(); // Scripts antes de </body>

            // Configuración adicional
            $table->boolean('maintenance_mode')->default(false);
            $table->text('maintenance_message')->nullable();
            $table->json('extra_settings')->nullable(); // Para configuraciones adicionales flexibles

            $table->timestamps();
        });

        // Insertar configuración por defecto
        DB::table('site_settings')->insert([
            'site_name' => 'iLeben',
            'site_description' => 'Sistema de gestión de proyectos y plantas',
            'primary_color' => '#667eea',
            'secondary_color' => '#764ba2',
            'background_color' => '#ffffff',
            'text_color' => '#1f2937',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
