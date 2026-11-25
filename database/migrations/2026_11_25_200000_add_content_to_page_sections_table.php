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
        if (! Schema::hasColumn('page_sections', 'content')) {
            Schema::table('page_sections', function (Blueprint $table): void {
                $table->longText('content')->nullable()->after('type');
            });
        }

        // Poblar contenido inicial desde data existente si existe texto/descripción
        $sections = DB::table('page_sections')->whereNull('content')->whereNotNull('data')->get();
        foreach ($sections as $section) {
            $data = json_decode($section->data, true);
            if (! is_array($data)) {
                continue;
            }

            $html = null;
            if (! empty($data['content'])) {
                $html = (string) $data['content'];
            } elseif (! empty($data['description'])) {
                $html = '<p>' . nl2br(e($data['description'])) . '</p>';
            } elseif (! empty($data['text'])) {
                $html = '<p>' . nl2br(e($data['text'])) . '</p>';
            } elseif (! empty($data['paragraphs']) && is_array($data['paragraphs'])) {
                $html = implode("\n", array_map(fn ($p) => '<p>' . $p . '</p>', $data['paragraphs']));
            }

            if ($html) {
                DB::table('page_sections')->where('id', $section->id)->update(['content' => $html]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('page_sections', 'content')) {
            Schema::table('page_sections', function (Blueprint $table): void {
                $table->dropColumn('content');
            });
        }
    }
};
