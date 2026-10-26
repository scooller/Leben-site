<?php

namespace Tests\Unit\Models;

use App\Models\Plant;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantTest extends TestCase
{
    use RefreshDatabase;

    public function test_plant_has_fillable_attributes(): void
    {
        $fillable = [
            'salesforce_product_id',
            'salesforce_proyecto_id',
            'asesor_id',
            'contact_link',
            'name',
            'product_code',
            'tipo_producto',
            'orientacion',
            'programa',
            'programa2',
            'piso',
            'precio_base',
            'precio_lista',
            'porcentaje_maximo_unidad',
            'descuento_defecto_cotizacion_web',
            'descuento_maximo_unidad',
            'descuento_iva',
            'priorizar_descuentos',
            'unidad_sale',
            'superficie_total_principal',
            'superficie_interior',
            'superficie_util',
            'superficie_terraza',
            'cover_image_id',
            'interior_image_id',
            'salesforce_interior_image_url',
            'is_active',
            'last_synced_at',
        ];

        $plant = new Plant;

        $this->assertEquals($fillable, $plant->getFillable());
    }

    public function test_plant_casts_attributes_correctly(): void
    {
        $plant = Plant::factory()->create([
            'precio_base' => '5000.50',
            'porcentaje_maximo_unidad' => '12.50',
            'descuento_maximo_unidad' => '10.00',
            'descuento_iva' => '5.00',
            'priorizar_descuentos' => 1,
            'unidad_sale' => 1,
            'superficie_total_principal' => '75.25',
            'is_active' => 1,
        ]);

        $this->assertIsString($plant->precio_base);
        $this->assertIsString($plant->porcentaje_maximo_unidad);
        $this->assertIsString($plant->descuento_maximo_unidad);
        $this->assertIsString($plant->descuento_iva);
        $this->assertIsBool($plant->priorizar_descuentos);
        $this->assertIsBool($plant->unidad_sale);
        $this->assertIsString($plant->superficie_total_principal);
        $this->assertIsBool($plant->is_active);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $plant->last_synced_at);
    }

    public function test_resolve_final_price_uses_project_discounts_by_default(): void
    {
        $proyecto = Proyecto::factory()->create([
            'descuento_defecto_cotizacion_web' => 10,
            'descuento_maximo_unidad' => 20,
            'descuento_iva' => 0,
        ]);

        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'precio_lista' => 1000,
            'precio_base' => 1000,
            'priorizar_descuentos' => false,
            'descuento_defecto_cotizacion_web' => 5,
        ]);

        // Default source is web_discount: 10% from project
        $this->assertEquals(900.0, $plant->resolveFinalPrice('web_discount'));
        // max_unit: 20% from project
        $this->assertEquals(800.0, $plant->resolveFinalPrice('max_unit'));
    }

    public function test_resolve_final_price_uses_plant_discounts_when_prioritized(): void
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
        ]);

        // When prioritized, web_discount + iva = 30 + 5 = 35% -> price 650
        $this->assertEquals(650.0, $plant->resolveFinalPrice('web_discount'));
        // max_unit + iva = 40 + 5 = 45% -> price 550
        $this->assertEquals(550.0, $plant->resolveFinalPrice('max_unit'));
    }

    public function test_plant_belongs_to_proyecto(): void
    {
        $proyecto = Proyecto::factory()->create();
        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
        ]);

        $this->assertInstanceOf(Proyecto::class, $plant->proyecto);
        $this->assertEquals($proyecto->id, $plant->proyecto->id);
    }

    public function test_plant_can_be_created_with_factory(): void
    {
        $plant = Plant::factory()->create();

        $this->assertInstanceOf(Plant::class, $plant);
        $this->assertDatabaseHas('plants', [
            'id' => $plant->id,
        ]);
    }

    public function test_it_resolves_final_price_using_default_discount_when_source_is_web_discount(): void
    {
        $proyecto = Proyecto::factory()->create([
            'descuento_defecto_cotizacion_web' => 25,
            'descuento_maximo_unidad' => 10,
        ]);

        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'precio_base' => 100,
            'precio_lista' => 200,
            'porcentaje_maximo_unidad' => 5,
        ]);

        $this->assertSame(150.0, $plant->resolveFinalPrice('web_discount'));
    }

    public function test_it_resolves_final_price_using_porcentaje_maximo_when_source_is_max_unit(): void
    {
        $proyecto = Proyecto::factory()->create([
            'descuento_defecto_cotizacion_web' => 25,
            'descuento_maximo_unidad' => 10,
        ]);

        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'precio_base' => 100,
            'precio_lista' => 200,
        ]);

        $this->assertSame(180.0, $plant->resolveFinalPrice('max_unit'));
    }
}
