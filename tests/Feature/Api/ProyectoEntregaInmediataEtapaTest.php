<?php

namespace Tests\Feature\Api;

use App\Models\Plant;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\WithApiToken;
use Tests\TestCase;

class ProyectoEntregaInmediataEtapaTest extends TestCase
{
    use RefreshDatabase;
    use WithApiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiToken();
    }

    public function test_proyectos_index_and_show_return_entrega_inmediata_as_etapa_when_flag_is_active(): void
    {
        $proyectoInmediato = Proyecto::factory()->create([
            'name' => 'Proyecto Inmediato',
            'etapa' => 'permiso_edificacion',
            'entrega_inmediata' => true,
            'is_active' => true,
        ]);

        $proyectoNormal = Proyecto::factory()->create([
            'name' => 'Proyecto Normal',
            'etapa' => 'inicio_obra',
            'entrega_inmediata' => false,
            'is_active' => true,
        ]);

        $responseIndex = $this->getJson('/api/v1/proyectos');
        $responseIndex->assertOk();

        $items = collect($responseIndex->json('data'));
        $itemInmediato = $items->firstWhere('id', $proyectoInmediato->id);
        $itemNormal = $items->firstWhere('id', $proyectoNormal->id);

        $this->assertNotNull($itemInmediato);
        $this->assertSame('Entrega inmediata', $itemInmediato['etapa']);
        $this->assertTrue((bool) $itemInmediato['entrega_inmediata']);

        $this->assertNotNull($itemNormal);
        $this->assertSame('inicio_obra', $itemNormal['etapa']);
        $this->assertFalse((bool) $itemNormal['entrega_inmediata']);

        $responseShowInmediato = $this->getJson('/api/v1/proyectos/'.$proyectoInmediato->id);
        $responseShowInmediato->assertOk();
        $this->assertSame('Entrega inmediata', $responseShowInmediato->json('etapa'));

        $responseShowNormal = $this->getJson('/api/v1/proyectos/'.$proyectoNormal->id);
        $responseShowNormal->assertOk();
        $this->assertSame('inicio_obra', $responseShowNormal->json('etapa'));
    }

    public function test_plants_index_and_location_filters_prioritize_entrega_inmediata(): void
    {
        $proyecto = Proyecto::factory()->create([
            'name' => 'Edificio Inmediato',
            'etapa' => 'obra_gruesa',
            'entrega_inmediata' => true,
            'is_active' => true,
        ]);

        Plant::query()->create([
            'salesforce_product_id' => (string) Str::uuid(),
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'name' => 'Depto 301',
            'product_code' => 'P-301',
            'tipo_producto' => 'DEPARTAMENTO',
            'precio_base' => 3500,
            'is_active' => true,
        ]);

        $responsePlants = $this->getJson('/api/v1/plantas');
        $responsePlants->assertOk();

        $plantItem = collect($responsePlants->json('data'))->firstWhere('name', 'Depto 301');
        $this->assertNotNull($plantItem);
        $this->assertSame('Entrega inmediata', $plantItem['proyecto']['etapa']);
        $this->assertTrue((bool) $plantItem['proyecto']['entrega_inmediata']);

        $responseFilters = $this->getJson('/api/v1/plantas/filtros-ubicacion');
        $responseFilters->assertOk();

        $entregas = $responseFilters->json('entregas');
        $this->assertContains('Entrega inmediata', $entregas);
    }
}
