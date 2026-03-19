<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncPlantsJob;
use App\Models\Proyecto;
use App\Services\Salesforce\SalesforceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Tests\TestCase;

class SyncPlantsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        SyncPlantsJob::dispatch();

        Queue::assertPushed(SyncPlantsJob::class);
    }

    public function test_job_creates_new_plants_on_handle(): void
    {
        $proyecto = Proyecto::factory()->create(['salesforce_id' => 'SF_PROJ_001']);

        Forrest::shouldReceive('authenticate')->once();

        $this->mock(SalesforceService::class, function (MockInterface $mock) use ($proyecto) {
            $mock->shouldReceive('findPlants')
                ->once()
                ->andReturn([
                    [
                        'id' => 'SF_PLANT_001',
                        'name' => 'Depto 101',
                        'product_code' => 'PLANT-101',
                        'orientacion' => 'Norte',
                        'programa' => '2 dormitorios',
                        'programa2' => '2 baños',
                        'piso' => '1',
                        'precio_base' => 5000.0,
                        'precio_lista' => 5500.0,
                        'superficie_total_principal' => 75.0,
                        'superficie_interior' => 65.0,
                        'superficie_util' => 60.0,
                        'opportunity_id' => null,
                        'superficie_terraza' => 10.0,
                        'superficie_vendible' => 75.0,
                        'proyecto_id' => $proyecto->salesforce_id,
                    ],
                ]);
        });

        (new SyncPlantsJob)->handle();

        $this->assertDatabaseHas('plants', [
            'salesforce_product_id' => 'SF_PLANT_001',
            'name' => 'Depto 101',
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
        ]);
    }

    public function test_job_logs_success_after_sync(): void
    {
        $proyecto = Proyecto::factory()->create(['salesforce_id' => 'SF_PROJ_002']);

        Forrest::shouldReceive('authenticate')->once();

        $this->mock(SalesforceService::class, function (MockInterface $mock) use ($proyecto) {
            $mock->shouldReceive('findPlants')
                ->once()
                ->andReturn([
                    [
                        'id' => 'SF_PLANT_002',
                        'name' => 'Depto 202',
                        'product_code' => 'PLANT-202',
                        'orientacion' => 'Sur',
                        'programa' => '1 dormitorio',
                        'programa2' => '1 baño',
                        'piso' => '2',
                        'precio_base' => 4000.0,
                        'precio_lista' => 4400.0,
                        'superficie_total_principal' => 50.0,
                        'superficie_interior' => 45.0,
                        'superficie_util' => 40.0,
                        'opportunity_id' => null,
                        'superficie_terraza' => 5.0,
                        'superficie_vendible' => 50.0,
                        'proyecto_id' => $proyecto->salesforce_id,
                    ],
                ]);
        });

        (new SyncPlantsJob)->handle();

        $this->assertDatabaseHas('plants', [
            'salesforce_product_id' => 'SF_PLANT_002',
        ]);
    }

    public function test_job_does_not_create_plants_when_none_found(): void
    {
        Forrest::shouldReceive('authenticate')->once();

        $this->mock(SalesforceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('findPlants')
                ->once()
                ->andReturn([]);
        });

        (new SyncPlantsJob)->handle();

        $this->assertDatabaseCount('plants', 0);
    }

    public function test_job_continues_when_forrest_authentication_fails(): void
    {
        $proyecto = Proyecto::factory()->create(['salesforce_id' => 'SF_PROJ_003']);

        Forrest::shouldReceive('authenticate')
            ->once()
            ->andThrow(new \Exception('Auth failed'));

        $this->mock(SalesforceService::class, function (MockInterface $mock) use ($proyecto) {
            $mock->shouldReceive('findPlants')
                ->once()
                ->andReturn([
                    [
                        'id' => 'SF_PLANT_003',
                        'name' => 'Depto 303',
                        'product_code' => 'PLANT-303',
                        'orientacion' => 'Este',
                        'programa' => '3 dormitorios',
                        'programa2' => '2 baños',
                        'piso' => '3',
                        'precio_base' => 7000.0,
                        'precio_lista' => 7700.0,
                        'superficie_total_principal' => 90.0,
                        'superficie_interior' => 80.0,
                        'superficie_util' => 75.0,
                        'opportunity_id' => null,
                        'superficie_terraza' => 15.0,
                        'superficie_vendible' => 90.0,
                        'proyecto_id' => $proyecto->salesforce_id,
                    ],
                ]);
        });

        (new SyncPlantsJob)->handle();

        $this->assertDatabaseHas('plants', [
            'salesforce_product_id' => 'SF_PLANT_003',
        ]);
    }
}
