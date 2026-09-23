<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Plants\Pages\ListPlants;
use App\Filament\Resources\Plants\Tables\PlantsTable;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\User;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantsTableNaturalSortingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_name_column_sorts_in_natural_numeric_order_asc_and_desc(): void
    {
        Plant::factory()->create(['name' => '1001']);
        Plant::factory()->create(['name' => '202']);
        Plant::factory()->create(['name' => '1003']);
        Plant::factory()->create(['name' => '21']);

        $table = PlantsTable::configure(new Table(new ListPlants()));
        $column = $table->getColumn('name');

        // ASC
        $queryAsc = Plant::query();
        $column->applySort($queryAsc, 'asc');
        $namesAsc = $queryAsc->pluck('name')->all();

        $this->assertSame(['21', '202', '1001', '1003'], $namesAsc);

        // DESC
        $queryDesc = Plant::query();
        $column->applySort($queryDesc, 'desc');
        $namesDesc = $queryDesc->pluck('name')->all();

        $this->assertSame(['1003', '1001', '202', '21'], $namesDesc);
    }

    public function test_price_columns_sort_numerically(): void
    {
        Plant::factory()->create(['precio_base' => 5000, 'precio_lista' => 5500]);
        Plant::factory()->create(['precio_base' => 2000, 'precio_lista' => 2200]);
        Plant::factory()->create(['precio_base' => 10000, 'precio_lista' => 11000]);

        $table = PlantsTable::configure(new Table(new ListPlants()));

        // precio_base ASC
        $queryBase = Plant::query();
        $table->getColumn('precio_base')->applySort($queryBase, 'asc');
        $this->assertEquals([2000, 5000, 10000], array_map('intval', $queryBase->pluck('precio_base')->all()));

        // precio_lista DESC
        $queryLista = Plant::query();
        $table->getColumn('precio_lista')->applySort($queryLista, 'desc');
        $this->assertEquals([11000, 5500, 2200], array_map('intval', $queryLista->pluck('precio_lista')->all()));
    }

    public function test_percentage_columns_sort_numerically(): void
    {
        Plant::factory()->create(['porcentaje_maximo_unidad' => 15.5]);
        Plant::factory()->create(['porcentaje_maximo_unidad' => 5.0]);
        Plant::factory()->create(['porcentaje_maximo_unidad' => 20.0]);

        $table = PlantsTable::configure(new Table(new ListPlants()));

        $query = Plant::query();
        $table->getColumn('porcentaje_maximo_unidad')->applySort($query, 'asc');
        $this->assertEquals([5.0, 15.5, 20.0], array_map('floatval', $query->pluck('porcentaje_maximo_unidad')->all()));
    }
}
