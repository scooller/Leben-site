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
        Schema::table('asesores', function (Blueprint $table) {
            if (! Schema::hasColumn('asesores', 'avatar_image_id')) {
                $table->unsignedBigInteger('avatar_image_id')
                    ->nullable()
                    ->after('avatar_url');

                $table->foreign('avatar_image_id')
                    ->references('id')
                    ->on('curator')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asesores', function (Blueprint $table) {
            if (Schema::hasColumn('asesores', 'avatar_image_id')) {
                $table->dropForeign(['avatar_image_id']);
                $table->dropColumn('avatar_image_id');
            }
        });
    }
};
