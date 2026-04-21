<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_channels', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('notification_email')->nullable();
            $table->json('form_fields')->nullable();
            $table->json('domain_patterns')->nullable();
            $table->timestamps();
        });

        // Seed initial channels — the 'default' channel catches all unresolved requests.
        DB::table('contact_channels')->insert([
            [
                'slug' => 'default',
                'name' => 'Default',
                'is_active' => true,
                'is_default' => true,
                'notification_email' => null,
                'form_fields' => null,
                'domain_patterns' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'sale',
                'name' => 'Sale',
                'is_active' => true,
                'is_default' => false,
                'notification_email' => null,
                'form_fields' => null,
                'domain_patterns' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'argomedo',
                'name' => 'Argomedo',
                'is_active' => true,
                'is_default' => false,
                'notification_email' => null,
                'form_fields' => null,
                'domain_patterns' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'capitanes',
                'name' => 'Capitanes',
                'is_active' => true,
                'is_default' => false,
                'notification_email' => null,
                'form_fields' => null,
                'domain_patterns' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_channels');
    }
};
