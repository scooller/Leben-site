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
        Schema::create('salesforce_opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('salesforce_id')->unique();
            $table->foreignId('broker_id')->nullable()->constrained('brokers')->nullOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->string('broker_salesforce_id')->nullable()->index();
            $table->string('proyecto_salesforce_id')->nullable()->index();
            $table->string('account_salesforce_id')->nullable()->index();
            $table->string('contact_salesforce_id')->nullable()->index();
            $table->string('owner_salesforce_id')->nullable()->index();
            $table->string('name');
            $table->string('broker_name')->nullable();
            $table->string('proyecto_name')->nullable();
            $table->string('stage_name')->nullable()->index();
            $table->string('forecast_category_name')->nullable()->index();
            $table->string('currency_iso_code', 10)->nullable();
            $table->decimal('amount', 16, 2)->nullable();
            $table->decimal('probability', 5, 2)->nullable();
            $table->boolean('is_closed')->default(false)->index();
            $table->boolean('is_won')->default(false)->index();
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_private')->default(false);
            $table->date('close_date')->nullable()->index();
            $table->timestamp('salesforce_created_at')->nullable()->index();
            $table->timestamp('salesforce_last_modified_at')->nullable();
            $table->timestamp('salesforce_system_modstamp')->nullable()->index();
            $table->timestamp('synced_at')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salesforce_opportunities');
    }
};
