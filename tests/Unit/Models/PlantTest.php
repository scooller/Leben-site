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
            'unidad_sale' => 1,
            'superficie_total_principal' => '75.25',
            'is_active' => 1,
        ]);

        $this->assertIsString($plant->precio_base);
        $this->assertIsString($plant->porcentaje_maximo_unidad);
        $this->assertIsBool($plant->unidad_sale);
        $this->assertIsString($plant->superficie_total_principal);
        $this->assertIsBool($plant->is_active);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $plant->last_synced_at);
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

    public function test_it_resolves_final_price_using_default_discount_when_sale_event_is_inactive(): void
    {
        $plant = Plant::factory()->create([
            'precio_base' => 100,
            'precio_lista' => 200,
            'descuento_defecto_cotizacion_web' => 25,
            'porcentaje_maximo_unidad' => 5,
        ]);

        $this->assertSame(150.0, $plant->resolveFinalPrice(false));
    }

    public function test_it_resolves_final_price_using_porcentaje_maximo_when_sale_event_is_active(): void
    {
        $plant = Plant::factory()->create([
            'precio_base' => 100,
            'precio_lista' => 200,
            'descuento_defecto_cotizacion_web' => 25,
            'porcentaje_maximo_unidad' => 10,
        ]);

        $this->assertSame(180.0, $plant->resolveFinalPrice(true));
    }
}
