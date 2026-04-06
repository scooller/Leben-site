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
        Schema::create('asesores', function (Blueprint $table) {
            $table->id();
            $table->string('salesforce_id')->nullable()->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('whatsapp_owner')->nullable();
            $table->string('avatar_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asesores');
    }
};
