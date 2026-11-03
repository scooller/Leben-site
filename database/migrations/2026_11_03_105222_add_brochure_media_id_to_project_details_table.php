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
        Schema::table('project_details', function (Blueprint $table): void {
            $table->unsignedBigInteger('brochure_media_id')->nullable()->after('brochure_pdf_url');

            $table->foreign('brochure_media_id')
                ->references('id')
                ->on('curator')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_details', function (Blueprint $table): void {
            $table->dropForeign(['brochure_media_id']);
            $table->dropColumn(['brochure_media_id']);
        });
    }
};
