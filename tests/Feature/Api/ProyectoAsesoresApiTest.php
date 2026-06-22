<?php

namespace Tests\Feature\Api;

use App\Models\Asesor;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\WithApiToken;
use Tests\TestCase;

class ProyectoAsesoresApiTest extends TestCase
{
    use RefreshDatabase;
    use WithApiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiToken();
    }

    public function test_show_does_not_include_asesores_by_default(): void
    {
        $proyecto = Proyecto::factory()->create();
        $asesor = Asesor::factory()->create(['is_active' => true]);
        $proyecto->asesores()->attach($asesor);

        $response = $this->getJson('/api/v1/proyectos/'.$proyecto->id);

        $response
            ->assertOk()
            ->assertJsonMissingPath('asesores');
    }

    public function test_show_includes_active_asesores_when_requested(): void
    {
        $proyecto = Proyecto::factory()->create();
        $asesor = Asesor::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan@example.com',
            'whatsapp_owner' => '+56912345678',
            'is_active' => true,
        ]);
        $proyecto->asesores()->attach($asesor);

        $response = $this->getJson('/api/v1/proyectos/'.$proyecto->id.'?include_asesores=1');

        $response
            ->assertOk()
            ->assertJsonPath('asesores.0.full_name', 'Juan Pérez')
            ->assertJsonPath('asesores.0.email', 'juan@example.com')
            ->assertJsonPath('asesores.0.whatsapp_owner', '+56912345678');
    }

    public function test_show_excludes_inactive_asesores(): void
    {
        $proyecto = Proyecto::factory()->create();
        $active = Asesor::factory()->create(['first_name' => 'Active', 'is_active' => true]);
        $inactive = Asesor::factory()->create(['first_name' => 'Inactive', 'is_active' => false]);
        $proyecto->asesores()->attach([$active, $inactive]);

        $response = $this->getJson('/api/v1/proyectos/'.$proyecto->id.'?include_asesores=1');

        $response->assertOk();

        $names = collect($response->json('asesores'))->pluck('first_name')->all();
        $this->assertContains('Active', $names);
        $this->assertNotContains('Inactive', $names);
    }

    public function test_asesores_payload_has_expected_structure(): void
    {
        $proyecto = Proyecto::factory()->create();
        $asesor = Asesor::factory()->create(['is_active' => true]);
        $proyecto->asesores()->attach($asesor);

        $response = $this->getJson('/api/v1/proyectos/'.$proyecto->id.'?include_asesores=1');

        $response->assertOk();

        $asesorData = $response->json('asesores.0');

        $this->assertSame(
            ['id', 'full_name', 'first_name', 'last_name', 'email', 'whatsapp_owner', 'resolved_avatar_url'],
            array_keys($asesorData)
        );
    }

    public function test_show_with_asesores_and_plantas_combined(): void
    {
        $proyecto = Proyecto::factory()->create(['salesforce_id' => 'SF-PROJ-01']);
        $asesor = Asesor::factory()->create(['is_active' => true]);

        $proyecto->asesores()->attach($asesor);

        \App\Models\Plant::query()->create([
            'salesforce_product_id' => (string) Str::uuid(),
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'name' => '101',
            'product_code' => 'PLANT-1001',
            'programa' => '2 dormitorios',
            'programa2' => '2 baños',
            'precio_base' => 5000,
            'precio_lista' => 5500,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/proyectos/'.$proyecto->id.'?include_plantas=1&include_asesores=1');

        $response
            ->assertOk()
            ->assertJsonPath('plantas.0.name', '101')
            ->assertJsonPath('asesores.0.id', $asesor->id);
    }

    public function test_plantas_in_proyecto_have_enriched_computed_fields(): void
    {
        $proyecto = Proyecto::factory()->create([
            'salesforce_id' => 'SF-ENRICH-01',
            'descuento_defecto_cotizacion_web' => 5.0,
        ]);
        $asesor = Asesor::factory()->create(['is_active' => true, 'first_name' => 'Advisor']);
        $proyecto->asesores()->attach($asesor);

        $plant = \App\Models\Plant::query()->create([
            'salesforce_product_id' => (string) Str::uuid(),
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'name' => '201',
            'product_code' => 'PLANT-2001',
            'programa' => '3 dormitorios',
            'precio_base' => 4000,
            'precio_lista' => 5000,
            'descuento_defecto_cotizacion_web' => null,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/proyectos/'.$proyecto->id.'?include_plantas=1');

        $response->assertOk();

        $plantData = $response->json('plantas.0');

        // precio_final: evento_sale off, discount from project's descuento_defecto_cotizacion_web (5%)
        // 5000 - (5000 * 5 / 100) = 4750
        $this->assertEquals(4750.0, $plantData['precio_final']);

        // is_available: no reservation or payment
        $this->assertTrue($plantData['is_available']);

        // is_paid: no completed reservation/payment
        $this->assertFalse($plantData['is_paid']);

        // descuento_defecto_cotizacion_web: inherited from proyecto (plant's is null)
        $this->assertEquals(5.0, $plantData['descuento_defecto_cotizacion_web']);

        // asesores: inherited from proyecto (plant has no asesor_id)
        $this->assertCount(1, $plantData['asesores']);
        $this->assertEquals('Advisor', $plantData['asesores'][0]['first_name']);

        // Resolved image URLs should exist
        $this->assertArrayHasKey('imageUrl', $plantData);
        $this->assertArrayHasKey('detailImageUrl', $plantData);
        $this->assertArrayHasKey('cover_image_url', $plantData);
        $this->assertArrayHasKey('interior_image_url', $plantData);
    }

    public function test_planta_with_own_asesor_uses_plant_asesor_over_project(): void
    {
        $proyecto = Proyecto::factory()->create(['salesforce_id' => 'SF-OWN-ADV-01']);
        $projectAdvisor = Asesor::factory()->create(['is_active' => true, 'first_name' => 'ProjectAdvisor']);
        $plantAdvisor = Asesor::factory()->create(['is_active' => true, 'first_name' => 'PlantAdvisor']);
        $proyecto->asesores()->attach($projectAdvisor);

        $plant = \App\Models\Plant::query()->create([
            'salesforce_product_id' => (string) Str::uuid(),
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'asesor_id' => $plantAdvisor->id,
            'name' => '301',
            'product_code' => 'PLANT-3001',
            'programa' => '2D',
            'precio_base' => 3000,
            'precio_lista' => 3500,
            'descuento_defecto_cotizacion_web' => 8.0,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/proyectos/'.$proyecto->id.'?include_plantas=1');

        $response->assertOk();

        $plantData = $response->json('plantas.0');

        // Plant has its own asesor → only that asesor appears
        $this->assertCount(1, $plantData['asesores']);
        $this->assertEquals('PlantAdvisor', $plantData['asesores'][0]['first_name']);

        // Plant has own descuento_defecto_cotizacion_web → uses it (not project's)
        $this->assertEquals(8.0, $plantData['descuento_defecto_cotizacion_web']);
    }
}
