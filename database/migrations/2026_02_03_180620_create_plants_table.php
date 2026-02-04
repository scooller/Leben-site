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
        Schema::create('plants', function (Blueprint $table) {
            $table->id();
            $table->string('salesforce_pricebook_id')->unique();
            $table->string('pricebook_name');
            $table->string('salesforce_entry_id')->unique();
            $table->string('entry_name');
            $table->decimal('unit_price', 12, 2);
            $table->string('product_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            $table->index('pricebook_name');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plants');
    }
};
