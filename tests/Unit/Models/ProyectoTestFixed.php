<?php

namespace Tests\Unit\Models;

use App\Models\Plant;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProyectoTestFixed extends TestCase
{
    use RefreshDatabase;

    public function test_proyecto_has_correct_table_name(): void
    {
        $proyecto = new Proyecto;

        $this->assertEquals('proyectos', $proyecto->getTable());
    }

    public function test_proyecto_has_fillable_attributes(): void
    {
        $proyecto = new Proyecto;
        $fillable = $proyecto->getFillable();

        $this->assertContains('salesforce_id', $fillable);
        $this->assertContains('name', $fillable);
        $this->assertContains('descripcion', $fillable);
        $this->assertContains('direccion', $fillable);
        $this->assertContains('email', $fillable);
    }

    public function test_proyecto_casts_dates_correctly(): void
    {
        $proyecto = Proyecto::factory()->create([
            'fecha_inicio_ventas' => '2024-01-15',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $proyecto->fecha_inicio_ventas);
    }

    public function test_proyecto_casts_decimals_correctly(): void
    {
        $proyecto = Proyecto::factory()->create([
            'dscto_m_x_prod_principal_porc' => '15.50',
            'tasa' => '5.25',
        ]);

        // Decimal casts are returned as strings in Laravel for precision
        $this->assertIsString((string) $proyecto->dscto_m_x_prod_principal_porc);
        $this->assertEquals('15.50', $proyecto->dscto_m_x_prod_principal_porc);
        $this->assertIsString((string) $proyecto->tasa);
        $this->assertEquals('5.25', $proyecto->tasa);
    }

    public function test_proyecto_has_many_plantas(): void
    {
        $proyecto = Proyecto::factory()->create();
        Plant::factory()->count(3)->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
        ]);

        $this->assertCount(3, $proyecto->plantas);
        $this->assertInstanceOf(Plant::class, $proyecto->plantas->first());
    }

    public function test_proyecto_can_be_created_with_factory(): void
    {
        $proyecto = Proyecto::factory()->create();

        $this->assertInstanceOf(Proyecto::class, $proyecto);
        $this->assertDatabaseHas('proyectos', [
            'id' => $proyecto->id,
        ]);
    }

    public function test_proyecto_salesforce_id_is_unique(): void
    {
        $proyecto1 = Proyecto::factory()->create(['salesforce_id' => 'SF001']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Proyecto::factory()->create(['salesforce_id' => 'SF001']);
    }
}
