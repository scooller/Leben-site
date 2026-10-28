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
            $table->string('virtual_tours_eyebrow', 150)->nullable()->after('brochure_pdf_url');
            $table->string('virtual_tours_title')->nullable()->after('virtual_tours_eyebrow');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_details', function (Blueprint $table): void {
            $table->dropColumn([
                'virtual_tours_eyebrow',
                'virtual_tours_title',
            ]);
        });
    }
};
