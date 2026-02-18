<?php

namespace Tests\Unit\Models;

use App\Models\Plant;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantTestFixed extends TestCase
{
    use RefreshDatabase;

    public function test_plant_has_fillable_attributes(): void
    {
        $fillable = [
            'salesforce_product_id',
            'salesforce_proyecto_id',
            'name',
            'product_code',
            'orientacion',
            'programa',
            'programa2',
            'piso',
            'precio_base',
            'precio_lista',
            'precio_venta',
            'superficie_total_principal',
            'superficie_interior',
            'superficie_util',
            'opportunity_id',
            'superficie_terraza',
            'superficie_vendible',
            'is_active',
            'last_synced_at',
        ];

        $plant = new Plant;

        $this->assertEquals($fillable, $plant->getFillable());
    }

    public function test_plant_casts_attributes_correctly(): void
    {
        $plant = Plant::factory()->create([
            'precio_venta' => '5000.50',
            'superficie_total_principal' => '75.25',
            'is_active' => 1,
        ]);

        // Decimal casts are returned as strings in Laravel for precision
        $this->assertIsString((string) $plant->precio_venta);
        $this->assertEquals('5000.50', $plant->precio_venta);
        $this->assertIsString((string) $plant->superficie_total_principal);
        $this->assertEquals('75.25', $plant->superficie_total_principal);
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
}
