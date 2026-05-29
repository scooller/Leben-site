<?php

namespace Tests\Feature\Api;

use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\WithApiToken;
use Tests\TestCase;

class ProyectoApiFiltersTest extends TestCase
{
    use RefreshDatabase;
    use WithApiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiToken();
    }

    public function test_it_filters_proyectos_by_region(): void
    {
        Proyecto::factory()->create(['region' => 'Metropolitana']);
        Proyecto::factory()->create(['region' => 'Valparaíso']);

        $response = $this->getJson('/api/v1/proyectos?region=Metropolitana&campos=id,region');

        $response->assertOk();
        $regions = collect($response->json('data'))->pluck('region')->unique()->values()->all();

        $this->assertSame(['Metropolitana'], $regions);
    }

    public function test_it_uses_default_field_projection_when_campos_is_missing(): void
    {
        Proyecto::factory()->create([
            'name' => 'Proyecto Default Fields',
            'direccion' => 'Av. Principal 123',
            'comuna' => 'Santiago',
            'pagina_web' => 'https://proyecto-default.test',
            'region' => 'Metropolitana',
        ]);

        $response = $this->getJson('/api/v1/proyectos');

        $response->assertOk();

        $item = $response->json('data.0');

        $this->assertSame(['id', 'name', 'direccion', 'comuna', 'pagina_web', 'image_url'], array_keys($item));
        $this->assertArrayNotHasKey('region', $item);
    }

    public function test_it_applies_field_projection_with_campos(): void
    {
        Proyecto::factory()->create([
            'name' => 'Proyecto API Campos',
            'comuna' => 'Santiago',
            'region' => 'Metropolitana',
            'direccion' => 'Direccion secreta',
        ]);

        $response = $this->getJson('/api/v1/proyectos?campos=id,name,comuna');

        $response->assertOk();

        $item = $response->json('data.0');

        $this->assertSame(['id', 'name', 'comuna'], array_keys($item));
        $this->assertArrayNotHasKey('region', $item);
    }

    public function test_it_applies_field_projection_with_spanish_alias_nombre(): void
    {
        Proyecto::factory()->create([
            'name' => 'Proyecto Alias Nombre',
            'comuna' => 'Santiago',
        ]);

        $response = $this->getJson('/api/v1/proyectos?campos=id,nombre,comuna');

        $response->assertOk();

        $item = $response->json('data.0');

        $this->assertSame(['id', 'name', 'comuna'], array_keys($item));
        $this->assertSame('Proyecto Alias Nombre', $item['name']);
    }

    public function test_show_does_not_include_plantas_by_default(): void
    {
        $proyecto = Proyecto::factory()->create();

        Plant::query()->create([
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

        $response = $this->getJson('/api/v1/proyectos/' . $proyecto->id);

        $response
            ->assertOk()
            ->assertJsonMissingPath('plantas');
    }

    public function test_show_includes_plantas_when_requested(): void
    {
        $proyecto = Proyecto::factory()->create();

        Plant::query()->create([
            'salesforce_product_id' => (string) Str::uuid(),
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'name' => '102',
            'product_code' => 'PLANT-1002',
            'programa' => '2 dormitorios',
            'programa2' => '2 baños',
            'precio_base' => 5200,
            'precio_lista' => 5700,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/proyectos/' . $proyecto->id . '?include_plantas=1');

        $response
            ->assertOk()
            ->assertJsonPath('plantas.0.name', '102');
    }

    public function test_it_exposes_project_discount_when_project_source_is_configured(): void
    {
        SiteSetting::current()->update([
            'extra_settings' => [
                'salesforce_discount_source' => 'project',
            ],
        ]);

        Proyecto::factory()->create([
            'name' => 'Proyecto Fuente Proyecto',
            'descuento_maximo_unidad' => 22,
        ]);

        $response = $this->getJson('/api/v1/proyectos?campos=id,name,descuento_defecto_cotizacion_web');

        $response->assertOk();
        $response->assertJsonPath('data.0.descuento_defecto_cotizacion_web', 22);
    }

    public function test_it_exposes_plant_discount_when_plant_source_is_configured_with_project_fallback(): void
    {
        SiteSetting::current()->update([
            'extra_settings' => [
                'salesforce_discount_source' => 'plant',
            ],
        ]);

        $project = Proyecto::factory()->create([
            'name' => 'Proyecto Fuente Planta',
            'descuento_maximo_unidad' => 35,
        ]);

        Plant::query()->create([
            'salesforce_product_id' => (string) Str::uuid(),
            'salesforce_proyecto_id' => $project->salesforce_id,
            'name' => 'A-101',
            'product_code' => 'PLANT-A101',
            'programa' => '2 dormitorios',
            'programa2' => '2 baños',
            'precio_base' => 5000,
            'precio_lista' => 5500,
            'porcentaje_maximo_unidad' => null,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/proyectos?campos=id,name,descuento_defecto_cotizacion_web');

        $response->assertOk();
        $response->assertJsonPath('data.0.descuento_defecto_cotizacion_web', 35);
    }
}
