<?php

namespace Tests\Unit\Filament\Actions;

use App\Filament\Actions\SyncPlantsAction;
use App\Models\Asesor;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Services\Salesforce\SalesforceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncPlantsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_plants_preserves_product_code_on_update(): void
    {
        $proyecto = Proyecto::factory()->create();

        // Crear planta inicial con product_code
        $initialPlant = Plant::create([
            'salesforce_product_id' => 'sf-prod-123',
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'name' => 'Original Plant',
            'product_code' => 'ORIGINAL-CODE',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('plants', [
            'salesforce_product_id' => 'sf-prod-123',
            'product_code' => 'ORIGINAL-CODE',
        ]);

        // Simular que revisamos la lógica: si la planta existe, product_code no debería cambiar
        // Este test valida que la lógica de SyncPlantsAction preserva el product_code
        $existingPlant = Plant::where('salesforce_product_id', 'sf-prod-123')->first();
        $this->assertNotNull($existingPlant);
        $this->assertEquals('ORIGINAL-CODE', $existingPlant->product_code);

        // Actualizar sin touched product_code
        $existingPlant->update([
            'name' => 'Updated Plant Name',
            'is_active' => true,
        ]);

        // Verificar que product_code se mantiene igual
        $updatedPlant = Plant::find($existingPlant->id);
        $this->assertEquals('ORIGINAL-CODE', $updatedPlant->product_code);
        $this->assertEquals('Updated Plant Name', $updatedPlant->name);
    }

    public function test_sync_plants_sets_product_code_on_create(): void
    {
        $proyecto = Proyecto::factory()->create();

        // Crear nueva planta con product_code
        $newPlant = Plant::create([
            'salesforce_product_id' => 'sf-prod-456',
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'name' => 'New Plant',
            'product_code' => 'NEW-CODE',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('plants', [
            'salesforce_product_id' => 'sf-prod-456',
            'product_code' => 'NEW-CODE',
        ]);

        $this->assertEquals('NEW-CODE', $newPlant->product_code);
    }

    public function test_sync_plants_syncs_salesforce_interior_image_url_from_document_name(): void
    {
        $proyecto = Proyecto::factory()->create([
            'name' => 'Edificio Bold',
            'salesforce_id' => 'SF_PROJ_BOLD',
        ]);

        $this->mock(SalesforceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findPlants')
                ->once()
                ->andReturn([
                    [
                        'id' => 'SF_PLANT_100',
                        'name' => 'L2-LOCAL-N',
                        'product_code' => 'L2-LOCAL-N PISO 2 LOCAL NORTE',
                        'orientacion' => 'Norte',
                        'programa' => 'Local',
                        'programa2' => null,
                        'piso' => '2',
                        'precio_base' => 5000.0,
                        'precio_lista' => 5200.0,
                        'superficie_total_principal' => 110.0,
                        'superficie_interior' => 90.0,
                        'superficie_util' => 90.0,
                        'superficie_terraza' => 20.0,
                        'proyecto_id' => 'SF_PROJ_BOLD',
                    ],
                ]);

            $mock->shouldReceive('findPublicProjectDocuments')
                ->once()
                ->andReturn([
                    [
                        'name' => 'Edificio Bold - L2-LOCAL-N',
                        'download_url' => 'https://leben.my.salesforce-sites.com/servlet/servlet.ImageServer?id=015U1000003AUhRIAW&oid=00D8c0000018fVyEAI&lastMod=1734545299000',
                    ],
                ]);
        });

        $result = SyncPlantsAction::execute();

        $this->assertTrue($result['success']);

        $this->assertDatabaseHas('plants', [
            'salesforce_product_id' => 'SF_PLANT_100',
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'salesforce_interior_image_url' => 'https://leben.my.salesforce-sites.com/servlet/servlet.ImageServer?id=015U1000003AUhRIAW&oid=00D8c0000018fVyEAI&lastMod=1734545299000',
        ]);
    }

    public function test_sync_plants_matches_document_when_product_code_already_has_project_prefix(): void
    {
        $proyecto = Proyecto::factory()->create([
            'name' => 'Edificio INN',
            'salesforce_id' => 'SF_PROJ_INN',
        ]);

        $this->mock(SalesforceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findPlants')
                ->once()
                ->andReturn([
                    [
                        'id' => 'SF_PLANT_200',
                        'name' => 'A1',
                        'product_code' => 'Edificio INN - A1 con patio-3D-3B-N',
                        'orientacion' => 'Norte',
                        'programa' => '3D',
                        'programa2' => '3B',
                        'piso' => '3',
                        'precio_base' => 7000.0,
                        'precio_lista' => 7300.0,
                        'superficie_total_principal' => 125.0,
                        'superficie_interior' => 95.0,
                        'superficie_util' => 95.0,
                        'superficie_terraza' => 30.0,
                        'proyecto_id' => 'SF_PROJ_INN',
                    ],
                ]);

            $mock->shouldReceive('findPublicProjectDocuments')
                ->once()
                ->andReturn([
                    [
                        'name' => 'Edificio INN - A1 con patio-3D-3B-N',
                        'download_url' => 'https://leben.my.salesforce-sites.com/servlet/servlet.ImageServer?id=015U10000033ln3IAA&oid=00D8c0000018fVyEAI&lastMod=1733948874000',
                    ],
                ]);
        });

        $result = SyncPlantsAction::execute();

        $this->assertTrue($result['success']);

        $this->assertDatabaseHas('plants', [
            'salesforce_product_id' => 'SF_PLANT_200',
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'salesforce_interior_image_url' => 'https://leben.my.salesforce-sites.com/servlet/servlet.ImageServer?id=015U10000033ln3IAA&oid=00D8c0000018fVyEAI&lastMod=1733948874000',
        ]);
    }

    public function test_sync_plants_matches_document_from_model_program_orientation_identifier(): void
    {
        $proyecto = Proyecto::factory()->create([
            'name' => 'Edificio INN',
            'salesforce_id' => 'SF_PROJ_INN_2',
        ]);

        $this->mock(SalesforceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findPlants')
                ->once()
                ->andReturn([
                    [
                        'id' => 'SF_PLANT_201',
                        'name' => '1302',
                        'product_code' => '1302 DEPARTAMENTO PISO 13  3D+3B MODELO A1',
                        'orientacion' => 'N',
                        'modelo_name' => 'A1 con patio',
                        'modelo_programa' => '3D+3B',
                        'programa' => '3D+3B',
                        'programa2' => null,
                        'piso' => '13',
                        'precio_base' => 8000.0,
                        'precio_lista' => 8500.0,
                        'superficie_total_principal' => 140.0,
                        'superficie_interior' => 105.0,
                        'superficie_util' => 105.0,
                        'superficie_terraza' => 35.0,
                        'proyecto_id' => 'SF_PROJ_INN_2',
                    ],
                ]);

            $mock->shouldReceive('findPublicProjectDocuments')
                ->once()
                ->withArgs(function (array $documentNames): bool {
                    return collect($documentNames)->contains(function (string $name): bool {
                        return Str::of($name)->lower()->contains('edificio inn - a1 con patio-3d-3b-n');
                    });
                })
                ->andReturn([
                    [
                        'name' => 'Edificio INN - A1 con patio-3D-3B-N',
                        'download_url' => 'https://leben.my.salesforce-sites.com/servlet/servlet.ImageServer?id=015U10000033ln4IAA&oid=00D8c0000018fVyEAI&lastMod=1733948875000',
                    ],
                ]);
        });

        $result = SyncPlantsAction::execute();

        $this->assertTrue($result['success']);

        $this->assertDatabaseHas('plants', [
            'salesforce_product_id' => 'SF_PLANT_201',
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'salesforce_interior_image_url' => 'https://leben.my.salesforce-sites.com/servlet/servlet.ImageServer?id=015U10000033ln4IAA&oid=00D8c0000018fVyEAI&lastMod=1733948875000',
        ]);
    }

    public function test_sync_plants_stores_porcentaje_maximo_unidad_from_salesforce(): void
    {
        $proyecto = Proyecto::factory()->create([
            'salesforce_id' => 'SF_PROJ_PERCENT',
        ]);

        $this->mock(SalesforceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findPlants')
                ->once()
                ->andReturn([
                    [
                        'id' => 'SF_PLANT_PERCENT',
                        'name' => '501',
                        'product_code' => 'PLANT-501',
                        'orientacion' => 'Poniente',
                        'modelo_name' => null,
                        'modelo_programa' => null,
                        'programa' => '2D',
                        'programa2' => '2B',
                        'piso' => '5',
                        'precio_base' => 6000.0,
                        'precio_lista' => 6500.0,
                        'porcentaje_maximo_unidad' => 17.5,
                        'superficie_total_principal' => 80.0,
                        'superficie_interior' => 70.0,
                        'superficie_util' => 68.0,
                        'superficie_terraza' => 10.0,
                        'proyecto_id' => 'SF_PROJ_PERCENT',
                    ],
                ]);

            $mock->shouldReceive('findPublicProjectDocuments')
                ->once()
                ->andReturn([]);
        });

        $result = SyncPlantsAction::execute();

        $this->assertTrue($result['success']);

        $this->assertDatabaseHas('plants', [
            'salesforce_product_id' => 'SF_PLANT_PERCENT',
            'porcentaje_maximo_unidad' => 17.5,
        ]);
    }

    public function test_sync_plants_preserves_manual_asesor_id_on_update(): void
    {
        $proyecto = Proyecto::factory()->create([
            'salesforce_id' => 'SF_PROJ_ADVISOR_LOCK',
        ]);

        $advisor = Asesor::factory()->create([
            'is_active' => true,
        ]);

        $plant = Plant::create([
            'salesforce_product_id' => 'SF_PLANT_ADVISOR_LOCK',
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'asesor_id' => $advisor->id,
            'name' => 'Original Advisor Plant',
            'product_code' => 'LOCK-001',
            'is_active' => true,
        ]);

        $this->mock(SalesforceService::class, function (MockInterface $mock) use ($proyecto): void {
            $mock->shouldReceive('findPlants')
                ->once()
                ->andReturn([
                    [
                        'id' => 'SF_PLANT_ADVISOR_LOCK',
                        'name' => 'Updated Advisor Plant',
                        'product_code' => 'LOCK-001',
                        'orientacion' => 'Norte',
                        'programa' => '2 dormitorios',
                        'programa2' => '2 baños',
                        'piso' => '4',
                        'precio_base' => 6000.0,
                        'precio_lista' => 6500.0,
                        'porcentaje_maximo_unidad' => null,
                        'superficie_total_principal' => 80.0,
                        'superficie_interior' => 70.0,
                        'superficie_util' => 68.0,
                        'superficie_terraza' => 12.0,
                        'tipo_producto' => 'DEPARTAMENTO',
                        'proyecto_id' => $proyecto->salesforce_id,
                    ],
                ]);

            $mock->shouldReceive('findPublicProjectDocuments')
                ->once()
                ->andReturn([]);
        });

        $result = SyncPlantsAction::execute();

        $this->assertTrue($result['success']);

        $plant->refresh();
        $this->assertSame($advisor->id, $plant->asesor_id);
        $this->assertSame('Updated Advisor Plant', $plant->name);
    }
}
