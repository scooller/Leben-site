<?php

namespace Tests\Feature\Api;

use App\Models\Plant;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiToken;
use Tests\TestCase;

class PlantDiscountPriorityApiTest extends TestCase
{
    use RefreshDatabase;
    use WithApiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiToken();
    }

    public function test_api_returns_project_discounts_when_not_prioritized(): void
    {
        $proyecto = Proyecto::factory()->create([
            'descuento_defecto_cotizacion_web' => 10,
            'descuento_maximo_unidad' => 20,
            'descuento_iva' => 2,
        ]);

        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'precio_lista' => 1000,
            'precio_base' => 1000,
            'priorizar_descuentos' => false,
            'descuento_defecto_cotizacion_web' => 30,
            'descuento_maximo_unidad' => 40,
            'descuento_iva' => 5,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/plantas/{$plant->id}");

        $response->assertOk();
        $response->assertJson([
            'priorizar_descuentos' => false,
            'descuento_defecto_cotizacion_web' => 10,
            'descuento_maximo_unidad' => 20,
            'descuento_iva' => 2,
            'precio_final' => 880, // 1000 - 12% (10 + 2)
        ]);
    }

    public function test_api_returns_plant_discounts_when_prioritized(): void
    {
        $proyecto = Proyecto::factory()->create([
            'descuento_defecto_cotizacion_web' => 10,
            'descuento_maximo_unidad' => 20,
            'descuento_iva' => 2,
        ]);

        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'precio_lista' => 1000,
            'precio_base' => 1000,
            'priorizar_descuentos' => true,
            'descuento_defecto_cotizacion_web' => 30,
            'descuento_maximo_unidad' => 40,
            'descuento_iva' => 5,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/plantas/{$plant->id}");

        $response->assertOk();
        $response->assertJson([
            'priorizar_descuentos' => true,
            'descuento_defecto_cotizacion_web' => 30,
            'descuento_maximo_unidad' => 40,
            'descuento_iva' => 5,
            'precio_final' => 650, // 1000 - 35% (30 + 5)
        ]);
    }

    public function test_api_plants_list_orders_by_effective_discounted_price(): void
    {
        $proyecto = Proyecto::factory()->create([
            'descuento_defecto_cotizacion_web' => 10,
            'descuento_maximo_unidad' => 10,
            'descuento_iva' => 0,
        ]);

        // Plant A: lista 1000, project discount 10% -> final price 900
        $plantA = Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'precio_lista' => 1000,
            'precio_base' => 1000,
            'priorizar_descuentos' => false,
            'is_active' => true,
        ]);

        // Plant B: lista 1000, prioritized plant discount 50% -> final price 500
        $plantB = Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'precio_lista' => 1000,
            'precio_base' => 1000,
            'priorizar_descuentos' => true,
            'descuento_defecto_cotizacion_web' => 50,
            'descuento_maximo_unidad' => 50,
            'descuento_iva' => 0,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/plantas');

        $response->assertOk();
        $data = $response->json('data');

        // Plant B should be first because effective price is 500 < 900
        $this->assertEquals($plantB->id, $data[0]['id']);
        $this->assertEquals($plantA->id, $data[1]['id']);
    }
}
