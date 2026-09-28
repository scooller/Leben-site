<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Plants\Pages\ListPlants;
use App\Filament\Resources\Plants\Tables\PlantsTable;
use App\Models\Plant;
use App\Models\User;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PlantsTableNotificationTitlesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_record_actions_report_single_quantity(): void
    {
        $table = PlantsTable::configure(new Table(new ListPlants()));

        $activePlant = new Plant(['is_active' => true]);
        $inactivePlant = new Plant(['is_active' => false]);
        $salePlant = new Plant(['unidad_sale' => true]);
        $nonSalePlant = new Plant(['unidad_sale' => false]);

        $toggleActiveAction = $table->getAction('toggleActive');
        $this->assertNotNull($toggleActiveAction);
        $closureActive = (fn () => $this->successNotificationTitle)->call($toggleActiveAction);
        $this->assertSame('1 planta activada', $closureActive($activePlant));
        $this->assertSame('1 planta desactivada', $closureActive($inactivePlant));

        $toggleSaleAction = $table->getAction('toggleUnidadSale');
        $this->assertNotNull($toggleSaleAction);
        $closureSale = (fn () => $this->successNotificationTitle)->call($toggleSaleAction);
        $this->assertSame('1 planta activada en Sale', $closureSale($salePlant));
        $this->assertSame('1 planta fuera de Sale', $closureSale($nonSalePlant));
    }

    public function test_bulk_actions_report_selected_record_counts(): void
    {
        $table = PlantsTable::configure(new Table(new ListPlants()));

        $oneRecord = new Collection([new Plant()]);
        $threeRecords = new Collection([new Plant(), new Plant(), new Plant()]);

        // activateSelected
        $activateBulk = $table->getBulkAction('activateSelected');
        $this->assertNotNull($activateBulk);
        $closure = (fn () => $this->successNotificationTitle)->call($activateBulk);
        $this->assertSame('1 planta activada', $closure($oneRecord));
        $this->assertSame('3 plantas activadas', $closure($threeRecords));

        // deactivateSelected
        $deactivateBulk = $table->getBulkAction('deactivateSelected');
        $this->assertNotNull($deactivateBulk);
        $closure = (fn () => $this->successNotificationTitle)->call($deactivateBulk);
        $this->assertSame('1 planta desactivada', $closure($oneRecord));
        $this->assertSame('3 plantas desactivadas', $closure($threeRecords));

        // activateSaleSelected
        $activateSaleBulk = $table->getBulkAction('activateSaleSelected');
        $this->assertNotNull($activateSaleBulk);
        $closure = (fn () => $this->successNotificationTitle)->call($activateSaleBulk);
        $this->assertSame('1 planta activada en Sale', $closure($oneRecord));
        $this->assertSame('3 plantas activadas en Sale', $closure($threeRecords));

        // deactivateSaleSelected
        $deactivateSaleBulk = $table->getBulkAction('deactivateSaleSelected');
        $this->assertNotNull($deactivateSaleBulk);
        $closure = (fn () => $this->successNotificationTitle)->call($deactivateSaleBulk);
        $this->assertSame('1 planta fuera de Sale', $closure($oneRecord));
        $this->assertSame('3 plantas fuera de Sale', $closure($threeRecords));

        // delete
        $deleteBulk = $table->getBulkAction('delete');
        $this->assertNotNull($deleteBulk);
        $closure = (fn () => $this->successNotificationTitle)->call($deleteBulk);
        $this->assertSame('1 planta eliminada', $closure($oneRecord));
        $this->assertSame('3 plantas eliminadas', $closure($threeRecords));
    }
}
