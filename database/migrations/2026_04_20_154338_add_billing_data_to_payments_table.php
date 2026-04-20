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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('billing_name')->nullable()->after('status');
            $table->string('billing_email')->nullable()->after('billing_name');
            $table->string('billing_phone', 20)->nullable()->after('billing_email');
            $table->string('billing_rut', 12)->nullable()->after('billing_phone');

            $table->index('billing_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['billing_email']);
            $table->dropColumn(['billing_name', 'billing_email', 'billing_phone', 'billing_rut']);
        });
    }
};
