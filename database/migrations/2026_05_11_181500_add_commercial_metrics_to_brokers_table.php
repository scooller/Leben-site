<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brokers', function (Blueprint $table) {
            $table->unsignedInteger('opportunities_total')->default(0)->after('salesforce_synced_at');
            $table->unsignedInteger('opportunities_open')->default(0)->after('opportunities_total');
            $table->unsignedInteger('opportunities_won')->default(0)->after('opportunities_open');
            $table->unsignedInteger('opportunities_lost')->default(0)->after('opportunities_won');
            $table->unsignedInteger('opportunities_total_30d')->default(0)->after('opportunities_lost');
            $table->unsignedInteger('opportunities_won_30d')->default(0)->after('opportunities_total_30d');
            $table->decimal('closure_rate_30d', 5, 2)->nullable()->after('opportunities_won_30d');
            $table->decimal('pipeline_amount_30d', 16, 2)->default(0)->after('closure_rate_30d');
            $table->decimal('won_amount_30d', 16, 2)->default(0)->after('pipeline_amount_30d');
            $table->timestamp('last_opportunity_at')->nullable()->after('won_amount_30d');
            $table->string('last_stage_name')->nullable()->after('last_opportunity_at');

            $table->index(['opportunities_won', 'opportunities_total_30d']);
            $table->index('last_opportunity_at');
        });
    }

    public function down(): void
    {
        Schema::table('brokers', function (Blueprint $table) {
            $table->dropIndex(['opportunities_won', 'opportunities_total_30d']);
            $table->dropIndex(['last_opportunity_at']);
            $table->dropColumn([
                'opportunities_total',
                'opportunities_open',
                'opportunities_won',
                'opportunities_lost',
                'opportunities_total_30d',
                'opportunities_won_30d',
                'closure_rate_30d',
                'pipeline_amount_30d',
                'won_amount_30d',
                'last_opportunity_at',
                'last_stage_name',
            ]);
        });
    }
};
