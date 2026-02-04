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
        Schema::create('salesforce_leads', function (Blueprint $table) {
            $table->id();
            $table->string('salesforce_id')->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('company')->nullable();
            $table->string('status')->nullable();
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            $table->index('salesforce_id');
            $table->index('email');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salesforce_leads');
    }
};
