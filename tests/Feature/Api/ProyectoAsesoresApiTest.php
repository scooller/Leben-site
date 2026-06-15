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
}
