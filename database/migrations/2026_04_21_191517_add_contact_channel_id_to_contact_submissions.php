<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('contact_submissions', 'contact_channel_id')) {
                // Nullable so existing rows without a channel are compatible (backward safe).
                $table->foreignId('contact_channel_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('contact_channels')
                    ->nullOnDelete();

                $table->index('contact_channel_id');
            } else {
                // Column exists (partial run from a previous failed attempt) — just add FK.
                $table->foreign('contact_channel_id')
                    ->references('id')
                    ->on('contact_channels')
                    ->nullOnDelete();

                if (! collect(DB::select("SHOW INDEX FROM contact_submissions WHERE Key_name = 'contact_submissions_contact_channel_id_index'"))->isNotEmpty()) {
                    $table->index('contact_channel_id');
                }
            }
        });

        // Back-fill existing rows to the default channel (if it exists).
        $defaultChannelId = DB::table('contact_channels')
            ->where('is_default', true)
            ->where('is_active', true)
            ->value('id');

        if ($defaultChannelId !== null) {
            DB::table('contact_submissions')
                ->whereNull('contact_channel_id')
                ->update(['contact_channel_id' => $defaultChannelId]);
        }
    }

    public function down(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->dropForeign(['contact_channel_id']);
            $table->dropIndex(['contact_channel_id']);
            $table->dropColumn('contact_channel_id');
        });
    }
};
