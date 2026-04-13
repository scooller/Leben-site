<?php

namespace Tests\Feature;

use App\Filament\Actions\SyncPlantsAction;
use App\Models\Proyecto;
use App\Services\Salesforce\SalesforceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SyncPlantsActionTipoProductoTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_tipo_producto_from_salesforce_when_syncing_plants(): void
    {
        $project = Proyecto::factory()->create([
            'salesforce_id' => 'a0P111111111111AAA',
            'name' => 'Proyecto Demo',
        ]);

        $salesforceService = Mockery::mock(SalesforceService::class);
        $salesforceService->shouldReceive('findPlants')
            ->once()
            ->andReturn([
                [
                    'id' => '01t111111111111AAA',
                    'proyecto_id' => $project->salesforce_id,
                    'name' => 'Planta 101',
                    'product_code' => 'PL-101',
                    'tipo_producto' => 'ESTACIONAMIENTO',
                    'orientacion' => 'Norte',
                    'programa' => '2D',
                    'programa2' => '2B',
                    'piso' => '1',
                    'precio_base' => 2200,
                    'precio_lista' => 2400,
                    'porcentaje_maximo_unidad' => 10,
                    'superficie_total_principal' => 60,
                    'superficie_interior' => 55,
                    'superficie_util' => 52,
                    'superficie_terraza' => 8,
                ],
            ]);
        $salesforceService->shouldReceive('findPublicProjectDocuments')
            ->andReturn([]);

        $this->app->instance(SalesforceService::class, $salesforceService);

        $result = SyncPlantsAction::execute();

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('plants', [
            'salesforce_product_id' => '01t111111111111AAA',
            'salesforce_proyecto_id' => $project->salesforce_id,
            'tipo_producto' => 'ESTACIONAMIENTO',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
