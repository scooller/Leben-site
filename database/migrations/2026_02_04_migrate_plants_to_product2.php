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
        Schema::dropIfExists('plants');

        Schema::create('plants', function (Blueprint $table) {
            $table->id();
            $table->string('salesforce_product_id')->unique();
            $table->string('name');
            $table->string('product_code');
            $table->string('orientacion')->nullable();
            $table->string('programa')->nullable();
            $table->string('programa2')->nullable();
            $table->string('piso')->nullable();
            $table->decimal('precio_base', 15, 2)->default(0);
            $table->decimal('precio_lista', 15, 2)->default(0);
            $table->decimal('precio_venta', 15, 2)->nullable();
            $table->decimal('superficie_total_principal', 10, 2)->default(0);
            $table->decimal('superficie_interior', 10, 2)->default(0);
            $table->decimal('superficie_util', 10, 2)->default(0);
            $table->string('opportunity_id')->nullable();
            $table->decimal('superficie_terraza', 10, 2)->default(0);
            $table->decimal('superficie_vendible', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            $table->index('name');
            $table->index(['programa', 'piso']);
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
