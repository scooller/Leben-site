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
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('pages');

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('badge', 100)->nullable();
            $table->foreignId('hero_image_id')->nullable()->constrained('curator')->nullOnDelete();
            $table->string('hero_image_url', 500)->nullable();
            $table->string('hero_video_url', 500)->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->foreignId('seo_og_image_id')->nullable()->constrained('curator')->nullOnDelete();
            $table->string('seo_og_image_url', 500)->nullable();
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('page_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('section_key', 100)->index();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('eyebrow', 100)->nullable();
            $table->string('type', 50);
            $table->json('data')->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('pages');
    }
};
