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
        Schema::create('salesforce_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('salesforce_id')->unique();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('billing_country')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('industry')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            $table->index('salesforce_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salesforce_accounts');
    }
};
