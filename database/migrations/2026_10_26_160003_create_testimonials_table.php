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
        Schema::dropIfExists('testimonials');

        Schema::create('testimonials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->string('name', 150);
            $table->string('role', 150)->nullable();
            $table->foreignId('avatar_id')->nullable()->constrained('curator')->nullOnDelete();
            $table->string('avatar_url', 500)->nullable();
            $table->decimal('rating', 2, 1)->default(5.0);
            $table->text('review_text');
            $table->string('review_date_human', 50)->nullable();
            $table->boolean('is_published')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
