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
        Schema::create('salesforce_queries_log', function (Blueprint $table) {
            $table->id();
            $table->text('soql');
            $table->integer('records_count')->default(0);
            $table->integer('execution_time_ms')->nullable();
            $table->string('status')->default('success'); // success, error
            $table->text('error_message')->nullable();
            $table->boolean('from_cache')->default(false);
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salesforce_queries_log');
    }
};
