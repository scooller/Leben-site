<?php

namespace Tests\Unit\Models;

use App\Models\Plant;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProyectoTest extends TestCase
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
        $this->assertContains('descuento_iva', $fillable);
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
            'valor_reserva_exigido_defecto_peso' => '150000.50',
            'descuento_iva' => '19.00',
        ]);

        $this->assertIsString($proyecto->valor_reserva_exigido_defecto_peso);
        $this->assertIsString($proyecto->descuento_iva);
        $this->assertSame('19.00', $proyecto->descuento_iva);
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

    public function test_it_normalizes_salesforce_stage_labels_to_canonical_values(): void
    {
        $this->assertSame('permiso_edificacion', Proyecto::normalizeEtapa('Permiso de edificación'));
        $this->assertSame('inicio_obra', Proyecto::normalizeEtapa('Inicio de obra'));
    }

    public function test_it_normalizes_legacy_stage_aliases_to_canonical_values(): void
    {
        $this->assertSame('obra_gruesa', Proyecto::normalizeEtapa('Construcción'));
        $this->assertSame('entrega', Proyecto::normalizeEtapa('Entrega Inmediata'));
        $this->assertNull(Proyecto::normalizeEtapa('Etapa inexistente'));
    }

    public function test_it_normalizes_mojibake_stage_values_to_canonical_values(): void
    {
        $this->assertSame('permiso_edificacion', Proyecto::normalizeEtapa('Permiso de edificaciÃ³n'));
        $this->assertSame('inicio_obra', Proyecto::normalizeEtapa('Inicio de obra'));
    }

    public function test_it_does_not_persist_null_when_stage_is_unknown(): void
    {
        $proyecto = Proyecto::factory()->create([
            'etapa' => 'Etapa ???',
        ]);

        $this->assertSame('Etapa ???', $proyecto->getRawOriginal('etapa'));
    }
}
