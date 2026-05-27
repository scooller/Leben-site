<?php

namespace Tests\Feature\Api;

use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSyncExportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_sync_export_requires_authentication(): void
    {
        $this->getJson('/api/v1/production-sync/export')
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_production_sync_export_requires_authorized_origin_when_token_has_authorized_url(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('production-sync', ['*']);
        $token->accessToken->forceFill([
            'authorized_url' => 'https://dev.ileben.cl',
        ])->save();

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
            'X-Authorized-Url' => 'https://otro-host.cl',
        ])->getJson('/api/v1/production-sync/export')
            ->assertStatus(403)
            ->assertJson([
                'message' => 'La URL de origen no está autorizada para este token.',
            ]);
    }

    public function test_production_sync_export_returns_payload_for_authorized_token(): void
    {
        $settings = SiteSetting::current();
        $settings->update([
            'site_name' => 'Leben QA',
            'extra_settings' => [
                'salesforce_oauth' => ['access_token' => 'secret'],
                'public_value' => 'ok',
                'hero_url' => 'https://example.com',
            ],
        ]);

        $project = Proyecto::factory()->create([
            'salesforce_id' => 'SF-PROJ-001',
            'name' => 'Proyecto Export',
        ]);

        Plant::factory()->create([
            'salesforce_product_id' => 'SF-PLANT-001',
            'salesforce_proyecto_id' => $project->salesforce_id,
            'name' => 'Planta Export',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('production-sync', ['*']);
        $token->accessToken->forceFill([
            'authorized_url' => 'https://dev.ileben.cl',
        ])->save();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
            'X-Authorized-Url' => 'https://dev.ileben.cl',
        ])->getJson('/api/v1/production-sync/export');

        $response->assertOk()
            ->assertJsonPath('site_settings.site_name', 'Leben QA')
            ->assertJsonPath('projects.0.salesforce_id', 'SF-PROJ-001')
            ->assertJsonPath('plants.0.salesforce_product_id', 'SF-PLANT-001')
            ->assertJsonMissingPath('site_settings.extra_settings.salesforce_oauth')
            ->assertJsonMissingPath('site_settings.extra_settings.hero_url')
            ->assertJsonPath('site_settings.extra_settings.public_value', 'ok');
    }
}
