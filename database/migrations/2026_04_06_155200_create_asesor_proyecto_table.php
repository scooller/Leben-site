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
        Schema::create('asesor_proyecto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('asesor_id')->constrained('asesores')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['proyecto_id', 'asesor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asesor_proyecto');
    }
};
