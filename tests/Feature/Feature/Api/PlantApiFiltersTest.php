<?php

namespace Tests\Feature\Feature\Api;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Asesor;
use App\Models\Payment;
use App\Models\Plant;
use App\Models\PlantReservation;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Models\User;
use Awcodes\Curator\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlantApiFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $this->withToken($user->createToken('plant-api-filters-test', ['*'])->plainTextToken);
    }

    public function test_it_filters_plants_by_proyecto_id(): void
    {
        $targetProject = Proyecto::factory()->create();
        $otherProject = Proyecto::factory()->create();

        $plantInTargetProject = $this->createPlant($targetProject->salesforce_id, true);

        $plantInOtherProject = $this->createPlant($otherProject->salesforce_id, true);

        $response = $this->getJson('/api/v1/plantas?proyecto_id='.$targetProject->id);

        $response->assertOk();
        $responsePlantIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($plantInTargetProject->id, $responsePlantIds);
        $this->assertNotContains($plantInOtherProject->id, $responsePlantIds);
    }

    public function test_it_returns_no_store_headers_for_plant_inventory_endpoint(): void
    {
        $project = Proyecto::factory()->create();
        $this->createPlant($project->salesforce_id, true);

        $response = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id);

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control', '');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
    }

    public function test_it_returns_no_store_headers_for_plant_reservation_status_endpoint(): void
    {
        $project = Proyecto::factory()->create();
        $plant = $this->createPlant($project->salesforce_id, true);

        $response = $this->getJson('/api/v1/reservations/planta/'.$plant->id);

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control', '');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
    }

    public function test_it_filters_plants_by_project_slug(): void
    {
        $targetProject = Proyecto::factory()->create([
            'name' => 'Edificio Argomedo',
            'slug' => 'edificio-argomedo',
        ]);
        $otherProject = Proyecto::factory()->create([
            'name' => 'Parque Central',
            'slug' => 'parque-central',
        ]);

        $plantInTargetProject = $this->createPlant($targetProject->salesforce_id, true);
        $plantInOtherProject = $this->createPlant($otherProject->salesforce_id, true);

        $response = $this->getJson('/api/v1/plantas?project_slug=edificio-argomedo');

        $response->assertOk();
        $responsePlantIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($plantInTargetProject->id, $responsePlantIds);
        $this->assertNotContains($plantInOtherProject->id, $responsePlantIds);
    }

    public function test_it_filters_plants_by_catalog_slug_using_project_slug(): void
    {
        $targetProject = Proyecto::factory()->create([
            'name' => 'Edificio Argomedo',
            'slug' => 'edificio-argomedo',
            'comuna' => 'Santiago',
        ]);
        $otherProject = Proyecto::factory()->create([
            'name' => 'Parque Central',
            'slug' => 'parque-central',
            'comuna' => 'La Florida',
        ]);

        $plantInTargetProject = $this->createPlant($targetProject->salesforce_id, true);
        $plantInOtherProject = $this->createPlant($otherProject->salesforce_id, true);

        $response = $this->getJson('/api/v1/plantas?catalog_slug=edificio-argomedo');

        $response->assertOk();
        $responsePlantIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($plantInTargetProject->id, $responsePlantIds);
        $this->assertNotContains($plantInOtherProject->id, $responsePlantIds);
    }

    public function test_it_filters_plants_by_catalog_slug_using_comuna_slug(): void
    {
        $projectInLaFlorida = Proyecto::factory()->create([
            'name' => 'Condominio Uno',
            'slug' => 'condominio-uno',
            'comuna' => 'La Florida',
        ]);
        $projectInProvidencia = Proyecto::factory()->create([
            'name' => 'Torre Dos',
            'slug' => 'torre-dos',
            'comuna' => 'Providencia',
        ]);

        $plantInLaFlorida = $this->createPlant($projectInLaFlorida->salesforce_id, true);
        $plantInProvidencia = $this->createPlant($projectInProvidencia->salesforce_id, true);

        $response = $this->getJson('/api/v1/plantas?catalog_slug=la-florida');

        $response->assertOk();
        $responsePlantIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($plantInLaFlorida->id, $responsePlantIds);
        $this->assertNotContains($plantInProvidencia->id, $responsePlantIds);
    }

    public function test_it_filters_plants_by_multiple_project_slugs_and_comuna_slug(): void
    {
        $projectInLaFlorida = Proyecto::factory()->create([
            'name' => 'Edificio Argomedo',
            'slug' => 'edificio-argomedo',
            'comuna' => 'La Florida',
        ]);
        $projectInProvidencia = Proyecto::factory()->create([
            'name' => 'Parque Central',
            'slug' => 'parque-central',
            'comuna' => 'Providencia',
        ]);
        $otherProjectInLaFlorida = Proyecto::factory()->create([
            'name' => 'Condominio Sur',
            'slug' => 'condominio-sur',
            'comuna' => 'La Florida',
        ]);

        $matchingPlant = $this->createPlant($projectInLaFlorida->salesforce_id, true);
        $this->createPlant($projectInProvidencia->salesforce_id, true);
        $this->createPlant($otherProjectInLaFlorida->salesforce_id, true);

        $response = $this->getJson('/api/v1/plantas?project_slug=edificio-argomedo,parque-central&comuna_slug=la-florida');

        $response->assertOk();
        $responsePlantIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$matchingPlant->id], $responsePlantIds);
    }

    public function test_it_filters_plants_by_availability(): void
    {
        $project = Proyecto::factory()->create();

        $availablePlant = $this->createPlant($project->salesforce_id, true);

        $reservedPlant = $this->createPlant($project->salesforce_id, true);

        PlantReservation::query()->create([
            'plant_id' => $reservedPlant->id,
            'session_token' => Str::random(64),
            'status' => ReservationStatus::ACTIVE,
            'expires_at' => now()->addMinutes(30),
        ]);

        $availableResponse = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id.'&disponible=1');
        $availableResponse->assertOk();
        $availablePlantIds = collect($availableResponse->json('data'))->pluck('id')->all();

        $this->assertContains($availablePlant->id, $availablePlantIds);
        $this->assertNotContains($reservedPlant->id, $availablePlantIds);

        $unavailableResponse = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id.'&disponible=0');
        $unavailableResponse->assertOk();
        $unavailablePlantIds = collect($unavailableResponse->json('data'))->pluck('id')->all();

        $this->assertContains($reservedPlant->id, $unavailablePlantIds);
        $this->assertNotContains($availablePlant->id, $unavailablePlantIds);
    }

    public function test_it_uses_site_settings_default_plants_per_page_when_per_page_is_not_provided(): void
    {
        SiteSetting::current()->update([
            'plants_per_page' => 5,
        ]);

        $project = Proyecto::factory()->create();

        for ($index = 0; $index < 7; $index++) {
            $this->createPlant($project->salesforce_id, true);
        }

        $response = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id);

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonPath('per_page', 5);
    }

    public function test_it_treats_paid_plants_as_unavailable(): void
    {
        $project = Proyecto::factory()->create();

        $availablePlant = $this->createPlant($project->salesforce_id, true);
        $paidPlant = $this->createPlant($project->salesforce_id, true);

        PlantReservation::query()->create([
            'plant_id' => $paidPlant->id,
            'session_token' => Str::random(64),
            'status' => ReservationStatus::COMPLETED,
            'expires_at' => now()->addMinutes(30),
            'completed_at' => now(),
        ]);

        $availableResponse = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id.'&disponible=1');
        $availableResponse->assertOk();
        $availablePlantIds = collect($availableResponse->json('data'))->pluck('id')->all();

        $this->assertContains($availablePlant->id, $availablePlantIds);
        $this->assertNotContains($paidPlant->id, $availablePlantIds);

        $unavailableResponse = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id.'&disponible=0');
        $unavailableResponse->assertOk();
        $unavailablePlantIds = collect($unavailableResponse->json('data'))->pluck('id')->all();

        $this->assertContains($paidPlant->id, $unavailablePlantIds);
        $this->assertNotContains($availablePlant->id, $unavailablePlantIds);
        $this->assertTrue((bool) collect($unavailableResponse->json('data'))
            ->firstWhere('id', $paidPlant->id)['is_paid']);
    }

    public function test_it_treats_completed_payments_related_to_the_plant_as_unavailable(): void
    {
        $project = Proyecto::factory()->create();

        $availablePlant = $this->createPlant($project->salesforce_id, true);
        $paidPlant = $this->createPlant($project->salesforce_id, true);

        Payment::query()->create([
            'user_id' => User::factory()->create()->id,
            'project_id' => $project->id,
            'plant_id' => $paidPlant->id,
            'gateway' => PaymentGateway::TRANSBANK->value,
            'gateway_tx_id' => (string) Str::uuid(),
            'amount' => 5000,
            'currency' => 'CLP',
            'status' => PaymentStatus::COMPLETED,
            'completed_at' => now(),
        ]);

        $availableResponse = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id.'&disponible=1');
        $availableResponse->assertOk();
        $availablePlantIds = collect($availableResponse->json('data'))->pluck('id')->all();

        $this->assertContains($availablePlant->id, $availablePlantIds);
        $this->assertNotContains($paidPlant->id, $availablePlantIds);

        $unavailableResponse = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id.'&disponible=0');
        $unavailableResponse->assertOk();
        $unavailablePlantIds = collect($unavailableResponse->json('data'))->pluck('id')->all();

        $this->assertContains($paidPlant->id, $unavailablePlantIds);
        $this->assertNotContains($availablePlant->id, $unavailablePlantIds);
        $this->assertTrue((bool) collect($unavailableResponse->json('data'))
            ->firstWhere('id', $paidPlant->id)['is_paid']);
    }

    public function test_it_keeps_pending_payments_related_to_the_plant_available(): void
    {
        $project = Proyecto::factory()->create();
        $plant = $this->createPlant($project->salesforce_id, true);

        Payment::query()->create([
            'user_id' => User::factory()->create()->id,
            'project_id' => $project->id,
            'plant_id' => $plant->id,
            'gateway' => PaymentGateway::TRANSBANK->value,
            'gateway_tx_id' => (string) Str::uuid(),
            'amount' => 5000,
            'currency' => 'CLP',
            'status' => PaymentStatus::PENDING,
        ]);

        $availableResponse = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id.'&disponible=1');
        $availableResponse->assertOk();

        $availablePlant = collect($availableResponse->json('data'))->firstWhere('id', $plant->id);

        $this->assertNotNull($availablePlant);
        $this->assertFalse((bool) $availablePlant['is_paid']);
        $this->assertTrue((bool) $availablePlant['is_available']);
    }

    public function test_it_filters_plants_by_comuna(): void
    {
        $projectInSantiago = Proyecto::factory()->create([
            'comuna' => 'Santiago',
            'is_active' => true,
        ]);

        $projectInProvidencia = Proyecto::factory()->create([
            'comuna' => 'Providencia',
            'is_active' => true,
        ]);

        $plantInSantiago = $this->createPlant($projectInSantiago->salesforce_id, true);
        $plantInProvidencia = $this->createPlant($projectInProvidencia->salesforce_id, true);

        $response = $this->getJson('/api/v1/plantas?comuna=Providencia');

        $response->assertOk();
        $responsePlantIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($plantInProvidencia->id, $responsePlantIds);
        $this->assertNotContains($plantInSantiago->id, $responsePlantIds);
    }

    public function test_it_returns_available_location_filters(): void
    {
        $projectInSantiago = Proyecto::factory()->create([
            'comuna' => 'Santiago',
            'region' => 'Metropolitana',
            'etapa' => 'entrega',
            'is_active' => true,
        ]);

        $projectInProvidencia = Proyecto::factory()->create([
            'comuna' => 'Providencia',
            'region' => 'Metropolitana',
            'etapa' => 'obra_gruesa',
            'is_active' => true,
        ]);

        $this->createPlant($projectInSantiago->salesforce_id, true, [
            'orientacion' => 'Norte',
            'tipo_producto' => 'DEPARTAMENTO',
            'piso' => '3',
        ]);
        $this->createPlant($projectInProvidencia->salesforce_id, true, [
            'orientacion' => 'Sur',
            'tipo_producto' => 'LOCAL',
            'piso' => '10',
        ]);

        $response = $this->getJson('/api/v1/plantas/filtros-ubicacion');

        $response->assertOk();
        $response->assertJsonFragment(['regions' => ['Metropolitana']]);
        $response->assertJsonFragment(['comunas' => ['Providencia', 'Santiago']]);
        $response->assertJsonFragment(['orientaciones' => ['Norte', 'Sur']]);
        $response->assertJsonFragment(['tipos_producto' => ['DEPARTAMENTO', 'LOCAL']]);
        $response->assertJsonFragment(['entregas' => ['Entrega', 'Obra gruesa']]);
        $response->assertJsonFragment(['pisos' => ['10', '3']]);
        $response->assertJsonPath('comunas_by_region.Metropolitana.0', 'Providencia');
        $response->assertJsonPath('comunas_by_region.Metropolitana.1', 'Santiago');
    }

    public function test_it_filters_plants_by_orientacion(): void
    {
        $project = Proyecto::factory()->create([
            'is_active' => true,
        ]);

        $northPlant = $this->createPlant($project->salesforce_id, true, ['orientacion' => 'Norte']);
        $southPlant = $this->createPlant($project->salesforce_id, true, ['orientacion' => 'Sur']);

        $response = $this->getJson('/api/v1/plantas?orientacion=Norte');

        $response->assertOk();
        $responsePlantIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($northPlant->id, $responsePlantIds);
        $this->assertNotContains($southPlant->id, $responsePlantIds);
    }

    public function test_it_filters_plants_by_tipo_producto(): void
    {
        $project = Proyecto::factory()->create([
            'is_active' => true,
        ]);

        $departmentPlant = $this->createPlant($project->salesforce_id, true, ['tipo_producto' => 'DEPARTAMENTO']);
        $localPlant = $this->createPlant($project->salesforce_id, true, ['tipo_producto' => 'LOCAL']);

        $response = $this->getJson('/api/v1/plantas?tipo_producto=LOCAL');

        $response->assertOk();
        $responsePlantIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($localPlant->id, $responsePlantIds);
        $this->assertNotContains($departmentPlant->id, $responsePlantIds);
    }

    public function test_it_filters_plants_by_tipo_producto_slug(): void
    {
        $project = Proyecto::factory()->create([
            'is_active' => true,
        ]);

        $departmentPlant = $this->createPlant($project->salesforce_id, true, ['tipo_producto' => 'DEPARTAMENTO']);
        $parkingPlant = $this->createPlant($project->salesforce_id, true, ['tipo_producto' => 'ESTACIONAMIENTO']);

        $response = $this->getJson('/api/v1/plantas?tipo_producto_slug=estacionamiento');

        $response->assertOk();
        $responsePlantIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($parkingPlant->id, $responsePlantIds);
        $this->assertNotContains($departmentPlant->id, $responsePlantIds);
    }

    public function test_it_filters_plants_by_multiple_tipo_producto_slugs(): void
    {
        $project = Proyecto::factory()->create([
            'is_active' => true,
        ]);

        $departmentPlant = $this->createPlant($project->salesforce_id, true, ['tipo_producto' => 'DEPARTAMENTO']);
        $storagePlant = $this->createPlant($project->salesforce_id, true, ['tipo_producto' => 'BODEGA']);
        $localPlant = $this->createPlant($project->salesforce_id, true, ['tipo_producto' => 'LOCAL']);

        $response = $this->getJson('/api/v1/plantas?tipo_producto_slug=departamento,bodega');

        $response->assertOk();
        $responsePlantIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($departmentPlant->id, $responsePlantIds);
        $this->assertContains($storagePlant->id, $responsePlantIds);
        $this->assertNotContains($localPlant->id, $responsePlantIds);
    }

    public function test_it_filters_plants_by_entrega_stage(): void
    {
        $deliveryProject = Proyecto::factory()->create([
            'etapa' => 'entrega',
            'is_active' => true,
        ]);

        $constructionProject = Proyecto::factory()->create([
            'etapa' => 'obra_gruesa',
            'is_active' => true,
        ]);

        $deliveryPlant = $this->createPlant($deliveryProject->salesforce_id, true);
        $constructionPlant = $this->createPlant($constructionProject->salesforce_id, true);

        $response = $this->getJson('/api/v1/plantas?entrega=Entrega');

        $response->assertOk();
        $responsePlantIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($deliveryPlant->id, $responsePlantIds);
        $this->assertNotContains($constructionPlant->id, $responsePlantIds);
    }

    public function test_it_filters_plants_for_evento_sale(): void
    {
        $project = Proyecto::factory()->create([
            'is_active' => true,
        ]);

        $plantMarkedForSale = $this->createPlant($project->salesforce_id, true, [
            'porcentaje_maximo_unidad' => null,
            'unidad_sale' => true,
        ]);

        $plantWithoutSaleFlag = $this->createPlant($project->salesforce_id, true, [
            'porcentaje_maximo_unidad' => 12.5,
            'unidad_sale' => false,
        ]);

        $response = $this->getJson('/api/v1/plantas?evento_sale=1');

        $response->assertOk();
        $responsePlantIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($plantMarkedForSale->id, $responsePlantIds);
        $this->assertNotContains($plantWithoutSaleFlag->id, $responsePlantIds);
    }

    public function test_it_excludes_plants_from_inactive_projects(): void
    {
        $activeProject = Proyecto::factory()->create([
            'is_active' => true,
        ]);

        $inactiveProject = Proyecto::factory()->create([
            'is_active' => false,
        ]);

        $activePlant = $this->createPlant($activeProject->salesforce_id, true);
        $inactiveProjectPlant = $this->createPlant($inactiveProject->salesforce_id, true);

        $response = $this->getJson('/api/v1/plantas');

        $response->assertOk();
        $responsePlantIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($activePlant->id, $responsePlantIds);
        $this->assertNotContains($inactiveProjectPlant->id, $responsePlantIds);
    }

    public function test_it_returns_limited_proyecto_fields_in_plantas_response(): void
    {
        $project = Proyecto::factory()->create([
            'name' => 'Proyecto API',
            'direccion' => 'Av. Siempre Viva 123',
            'comuna' => 'Santiago',
            'pagina_web' => 'https://proyecto.test',
            'region' => 'Metropolitana',
            'email' => 'hidden@example.com',
        ]);

        $this->createPlant($project->salesforce_id, true);

        $response = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id);

        $response->assertOk();

        $proyectoPayload = $response->json('data.0.proyecto');

        $this->assertArrayHasKey('id', $proyectoPayload);
        $this->assertArrayHasKey('name', $proyectoPayload);
        $this->assertArrayHasKey('direccion', $proyectoPayload);
        $this->assertArrayHasKey('comuna', $proyectoPayload);
        $this->assertArrayHasKey('pagina_web', $proyectoPayload);

        $this->assertSame('Proyecto API', $proyectoPayload['name']);
        $this->assertArrayNotHasKey('email', $proyectoPayload);
    }

    public function test_it_returns_image_urls_instead_of_image_ids(): void
    {
        $project = Proyecto::factory()->create();

        $this->createPlant($project->salesforce_id, true);

        $response = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id);

        $response->assertOk();
        $plantPayload = $response->json('data.0');

        $this->assertArrayHasKey('cover_image_url', $plantPayload);
        $this->assertArrayHasKey('interior_image_url', $plantPayload);
        $this->assertArrayNotHasKey('cover_image_id', $plantPayload);
        $this->assertArrayNotHasKey('interior_image_id', $plantPayload);
    }

    public function test_it_returns_contact_link_in_plant_payload(): void
    {
        $project = Proyecto::factory()->create();

        $plant = Plant::query()->create([
            'salesforce_product_id' => (string) Str::uuid(),
            'salesforce_proyecto_id' => $project->salesforce_id,
            'name' => 'A-100',
            'product_code' => 'PLANT-A100',
            'programa' => '2 dormitorios',
            'programa2' => '2 baños',
            'precio_base' => 5000,
            'precio_lista' => 5500,
            'contact_link' => 'https://wa.me/56912345678',
            'is_active' => true,
            'last_synced_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plantas/'.$plant->id);

        $response->assertOk();
        $response->assertJsonPath('contact_link', 'https://wa.me/56912345678');
    }

    public function test_it_returns_compact_media_payload_for_images(): void
    {
        $project = Proyecto::factory()->create();
        $media = Media::query()->create([
            'disk' => 'curator',
            'directory' => null,
            'visibility' => 'public',
            'name' => 'modelo-a1-101',
            'path' => 'modelo-a1-101.jpg',
            'width' => 350,
            'height' => 250,
            'size' => 52355,
            'type' => 'image/jpeg',
            'ext' => 'jpg',
            'title' => 'Modelo A1 - 101',
        ]);

        Plant::query()->create([
            'salesforce_product_id' => (string) Str::uuid(),
            'salesforce_proyecto_id' => $project->salesforce_id,
            'name' => '101',
            'product_code' => 'PLANT-101',
            'programa' => '2 dormitorios',
            'programa2' => '2 baños',
            'precio_base' => 5000,
            'precio_lista' => 5500,
            'cover_image_id' => $media->id,
            'interior_image_id' => $media->id,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id);

        $response->assertOk();

        $coverImageMedia = $response->json('data.0.cover_image_media');
        $interiorImageMedia = $response->json('data.0.interior_image_media');

        $this->assertSame('image/jpeg', $coverImageMedia['type']);
        $this->assertSame('Modelo A1 - 101', $coverImageMedia['title']);
        $this->assertArrayHasKey('url', $coverImageMedia);
        $this->assertArrayHasKey('thumbnail_url', $coverImageMedia);
        $this->assertArrayHasKey('medium_url', $coverImageMedia);
        $this->assertArrayHasKey('large_url', $coverImageMedia);
        $this->assertArrayNotHasKey('disk', $coverImageMedia);
        $this->assertArrayNotHasKey('path', $coverImageMedia);

        $this->assertSame('image/jpeg', $interiorImageMedia['type']);
        $this->assertSame('Modelo A1 - 101', $interiorImageMedia['title']);
        $this->assertArrayHasKey('url', $interiorImageMedia);
        $this->assertArrayHasKey('thumbnail_url', $interiorImageMedia);
        $this->assertArrayHasKey('medium_url', $interiorImageMedia);
        $this->assertArrayHasKey('large_url', $interiorImageMedia);
        $this->assertArrayNotHasKey('disk', $interiorImageMedia);
        $this->assertArrayNotHasKey('path', $interiorImageMedia);
    }

    public function test_it_can_get_plant_by_project_slug_and_unit_name(): void
    {
        $project = Proyecto::factory()->create([
            'name' => 'Edificio Inn',
            'is_active' => true,
        ]);

        $plant = Plant::query()->create([
            'salesforce_product_id' => (string) Str::uuid(),
            'salesforce_proyecto_id' => $project->salesforce_id,
            'name' => '202',
            'product_code' => 'PLANT-202',
            'programa' => '2 dormitorios',
            'programa2' => '2 baños',
            'precio_base' => 5000,
            'precio_lista' => 5500,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plantas/proyecto/'.$project->slug.'/unidad/202');

        $response->assertOk();
        $response->assertJsonPath('id', $plant->id);
        $response->assertJsonPath('proyecto.slug', $project->slug);
    }

    public function test_it_can_get_plant_by_project_slug_and_slugified_unit_name(): void
    {
        $project = Proyecto::factory()->create([
            'name' => 'Condominio Sur',
            'is_active' => true,
        ]);

        $plant = Plant::query()->create([
            'salesforce_product_id' => (string) Str::uuid(),
            'salesforce_proyecto_id' => $project->salesforce_id,
            'name' => 'Depto 120 B',
            'product_code' => 'PLANT-120B',
            'programa' => '2 dormitorios',
            'programa2' => '2 baños',
            'precio_base' => 5000,
            'precio_lista' => 5500,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plantas/proyecto/'.$project->slug.'/unidad/depto-120-b');

        $response->assertOk();
        $response->assertJsonPath('id', $plant->id);
    }

    public function test_it_returns_project_advisors_in_plant_payload(): void
    {
        $project = Proyecto::factory()->create([
            'is_active' => true,
        ]);

        $logoMedia = Media::query()->create([
            'disk' => 'curator',
            'directory' => null,
            'visibility' => 'public',
            'name' => 'branding-logo',
            'path' => 'branding-logo.png',
            'width' => 320,
            'height' => 120,
            'size' => 10240,
            'type' => 'image/png',
            'ext' => 'png',
            'title' => 'Branding Logo',
        ]);

        SiteSetting::query()->update([
            'logo_id' => $logoMedia->id,
        ]);

        $advisorAvatarMedia = Media::query()->create([
            'disk' => 'curator',
            'directory' => null,
            'visibility' => 'public',
            'name' => 'advisor-avatar',
            'path' => 'advisor-avatar.png',
            'width' => 320,
            'height' => 320,
            'size' => 10240,
            'type' => 'image/png',
            'ext' => 'png',
            'title' => 'Advisor Avatar',
        ]);

        $advisor = Asesor::factory()->create([
            'first_name' => 'Camila',
            'last_name' => 'Diaz',
            'email' => 'camila@example.com',
            'whatsapp_owner' => '+56 9 8765 4321',
            'avatar_image_id' => $advisorAvatarMedia->id,
            'is_active' => true,
        ]);

        $project->asesores()->attach($advisor->id);

        $plant = Plant::query()->create([
            'salesforce_product_id' => (string) Str::uuid(),
            'salesforce_proyecto_id' => $project->salesforce_id,
            'name' => '302',
            'product_code' => 'PLANT-302',
            'programa' => '2 dormitorios',
            'programa2' => '2 baños',
            'precio_base' => 5000,
            'precio_lista' => 5500,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plantas/'.$plant->id);

        $response->assertOk();
        $response->assertJsonPath('proyecto.asesores.0.id', $advisor->id);
        $response->assertJsonPath('proyecto.asesores.0.first_name', 'Camila');
        $response->assertJsonPath('proyecto.asesores.0.last_name', 'Diaz');
        $response->assertJsonPath('proyecto.asesores.0.email', 'camila@example.com');
        $response->assertJsonPath('proyecto.asesores.0.whatsapp_owner', '+56 9 8765 4321');
        $response->assertJsonPath('proyecto.asesores.0.whatsapp_redirect_url', route('advisors.whatsapp.redirect', ['asesor' => $advisor]));
        $response->assertJsonPath('proyecto.asesores.0.avatar_manual_url', $advisorAvatarMedia->url);
        $response->assertJsonPath('proyecto.asesores.0.avatar_url', $advisorAvatarMedia->url);
        $response->assertJsonPath('asesores.0.id', $advisor->id);
    }

    public function test_it_prioritizes_plant_advisor_over_project_advisors_in_payload(): void
    {
        $project = Proyecto::factory()->create([
            'is_active' => true,
        ]);

        $logoMedia = Media::query()->create([
            'disk' => 'curator',
            'directory' => null,
            'visibility' => 'public',
            'name' => 'branding-logo-2',
            'path' => 'branding-logo-2.png',
            'width' => 320,
            'height' => 120,
            'size' => 10240,
            'type' => 'image/png',
            'ext' => 'png',
            'title' => 'Branding Logo 2',
        ]);

        SiteSetting::query()->update([
            'logo_id' => $logoMedia->id,
        ]);

        $projectAdvisor = Asesor::factory()->create([
            'first_name' => 'Camila',
            'last_name' => 'Diaz',
            'email' => 'camila@example.com',
            'whatsapp_owner' => '+56 9 8765 4321',
            'is_active' => true,
        ]);

        $plantAdvisor = Asesor::factory()->create([
            'first_name' => 'Jorge',
            'last_name' => 'Paz',
            'email' => 'jorge@example.com',
            'whatsapp_owner' => '+56 9 1111 1111',
            'is_active' => true,
        ]);

        $project->asesores()->attach($projectAdvisor->id);

        $plant = Plant::query()->create([
            'salesforce_product_id' => (string) Str::uuid(),
            'salesforce_proyecto_id' => $project->salesforce_id,
            'asesor_id' => $plantAdvisor->id,
            'name' => '401',
            'product_code' => 'PLANT-401',
            'programa' => '2 dormitorios',
            'programa2' => '2 baños',
            'precio_base' => 5000,
            'precio_lista' => 5500,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plantas/'.$plant->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'asesores');
        $response->assertJsonPath('asesores.0.id', $plantAdvisor->id);
        $response->assertJsonPath('asesores.0.first_name', 'Jorge');
        $response->assertJsonPath('asesores.0.last_name', 'Paz');
        $response->assertJsonPath('asesores.0.email', 'jorge@example.com');

        $response->assertJsonPath('proyecto.asesores.0.id', $projectAdvisor->id);
    }

    public function test_it_orders_plants_by_precio_final_using_default_discount_when_sale_event_is_inactive(): void
    {
        $project = Proyecto::factory()->create([
            'is_active' => true,
            'descuento_defecto_cotizacion_web' => 10,
        ]);

        $this->createPlant($project->salesforce_id, true, [
            'name' => 'HIGH',
            'precio_base' => 50,
            'precio_lista' => 250,
            'porcentaje_maximo_unidad' => 1,
        ]);

        $this->createPlant($project->salesforce_id, true, [
            'name' => 'LOW',
            'precio_base' => 100,
            'precio_lista' => 100,
            'porcentaje_maximo_unidad' => 1,
        ]);

        $this->createPlant($project->salesforce_id, true, [
            'name' => 'MID',
            'precio_base' => 150,
            'precio_lista' => 150,
            'porcentaje_maximo_unidad' => 1,
        ]);

        $response = $this->getJson('/api/v1/plantas?project_slug='.$project->slug.'&perPage=50');

        $response->assertOk();
        $this->assertSame(['LOW', 'MID', 'HIGH'], collect($response->json('data'))->pluck('name')->all());
    }

    public function test_it_orders_plants_by_precio_final_using_porcentaje_maximo_when_sale_event_is_active(): void
    {
        $project = Proyecto::factory()->create([
            'is_active' => true,
            'descuento_defecto_cotizacion_web' => 40,
        ]);

        $this->createPlant($project->salesforce_id, true, [
            'name' => 'HIGH',
            'precio_base' => 50,
            'precio_lista' => 250,
            'porcentaje_maximo_unidad' => 10,
            'unidad_sale' => true,
        ]);

        $this->createPlant($project->salesforce_id, true, [
            'name' => 'LOW',
            'precio_base' => 100,
            'precio_lista' => 100,
            'porcentaje_maximo_unidad' => 20,
            'unidad_sale' => true,
        ]);

        $this->createPlant($project->salesforce_id, true, [
            'name' => 'MID',
            'precio_base' => 150,
            'precio_lista' => 150,
            'porcentaje_maximo_unidad' => 10,
            'unidad_sale' => true,
        ]);

        $response = $this->getJson('/api/v1/plantas?project_slug='.$project->slug.'&perPage=50&evento_sale=1');

        $response->assertOk();
        $this->assertSame(['LOW', 'MID', 'HIGH'], collect($response->json('data'))->pluck('name')->all());
    }

    public function test_it_orders_plants_by_offer_discount_desc_using_final_price_source(): void
    {
        SiteSetting::current()->update([
            'extra_settings' => [
                'price_source' => 'final',
            ],
        ]);

        $project = Proyecto::factory()->create([
            'is_active' => true,
            'descuento_defecto_cotizacion_web' => 0,
        ]);

        $this->createPlant($project->salesforce_id, true, [
            'name' => 'LOW-DISCOUNT',
            'precio_base' => 1000,
            'precio_lista' => 1000,
            'porcentaje_maximo_unidad' => 5,
            'unidad_sale' => true,
        ]);

        $this->createPlant($project->salesforce_id, true, [
            'name' => 'HIGH-DISCOUNT',
            'precio_base' => 1000,
            'precio_lista' => 1000,
            'porcentaje_maximo_unidad' => 20,
            'unidad_sale' => true,
        ]);

        $this->createPlant($project->salesforce_id, true, [
            'name' => 'MID-DISCOUNT',
            'precio_base' => 1000,
            'precio_lista' => 1000,
            'porcentaje_maximo_unidad' => 10,
            'unidad_sale' => true,
        ]);

        $response = $this->getJson('/api/v1/plantas?project_slug='.$project->slug.'&perPage=50&evento_sale=1&sort_by=offer_discount&sort_direction=desc');

        $response->assertOk();
        $this->assertSame(['HIGH-DISCOUNT', 'MID-DISCOUNT', 'LOW-DISCOUNT'], collect($response->json('data'))->pluck('name')->all());
    }

    public function test_it_orders_plants_by_offer_discount_desc_using_base_price_source(): void
    {
        SiteSetting::current()->update([
            'extra_settings' => [
                'price_source' => 'base',
            ],
        ]);

        $project = Proyecto::factory()->create([
            'is_active' => true,
            'descuento_defecto_cotizacion_web' => 0,
        ]);

        $this->createPlant($project->salesforce_id, true, [
            'name' => 'TEN-PERCENT',
            'precio_base' => 900,
            'precio_lista' => 1000,
            'porcentaje_maximo_unidad' => 0,
        ]);

        $this->createPlant($project->salesforce_id, true, [
            'name' => 'THIRTY-PERCENT',
            'precio_base' => 700,
            'precio_lista' => 1000,
            'porcentaje_maximo_unidad' => 0,
        ]);

        $this->createPlant($project->salesforce_id, true, [
            'name' => 'TWENTY-PERCENT',
            'precio_base' => 800,
            'precio_lista' => 1000,
            'porcentaje_maximo_unidad' => 0,
        ]);

        $response = $this->getJson('/api/v1/plantas?project_slug='.$project->slug.'&perPage=50&sort_by=offer_discount&sort_direction=desc');

        $response->assertOk();
        $this->assertSame(['THIRTY-PERCENT', 'TWENTY-PERCENT', 'TEN-PERCENT'], collect($response->json('data'))->pluck('name')->all());
    }

    public function test_it_returns_precio_final_field_based_on_evento_sale_state(): void
    {
        $project = Proyecto::factory()->create([
            'is_active' => true,
            'descuento_defecto_cotizacion_web' => 25,
        ]);

        $plant = $this->createPlant($project->salesforce_id, true, [
            'precio_base' => 100,
            'precio_lista' => 200,
            'porcentaje_maximo_unidad' => 10,
            'unidad_sale' => true,
        ]);

        $withoutSaleResponse = $this->getJson('/api/v1/plantas/'.$plant->id);
        $withoutSaleResponse->assertOk();
        $withoutSaleResponse->assertJsonPath('precio_final', 150);

        $withSaleResponse = $this->getJson('/api/v1/plantas/'.$plant->id.'?evento_sale=1');
        $withSaleResponse->assertOk();
        $withSaleResponse->assertJsonPath('precio_final', 180);
    }

    public function test_it_uses_project_discount_source_for_api_price_and_project_payload_when_configured(): void
    {
        SiteSetting::current()->update([
            'extra_settings' => [
                'salesforce_discount_source' => 'project',
            ],
        ]);

        $project = Proyecto::factory()->create([
            'is_active' => true,
            'descuento_maximo_unidad' => 30,
        ]);

        $plant = $this->createPlant($project->salesforce_id, true, [
            'precio_base' => 100,
            'precio_lista' => 200,
            'porcentaje_maximo_unidad' => 10,
            'unidad_sale' => true,
        ]);

        $response = $this->getJson('/api/v1/plantas/'.$plant->id.'?evento_sale=1');

        $response->assertOk();
        $response->assertJsonPath('precio_final', 140);
        $response->assertJsonPath('proyecto.descuento_defecto_cotizacion_web', 30);
    }

    public function test_it_falls_back_to_project_discount_when_plant_discount_is_missing_and_plant_source_is_configured(): void
    {
        SiteSetting::current()->update([
            'extra_settings' => [
                'salesforce_discount_source' => 'plant',
            ],
        ]);

        $project = Proyecto::factory()->create([
            'is_active' => true,
            'descuento_maximo_unidad' => 35,
        ]);

        $plant = $this->createPlant($project->salesforce_id, true, [
            'precio_base' => 100,
            'precio_lista' => 200,
            'porcentaje_maximo_unidad' => null,
            'unidad_sale' => true,
        ]);

        $response = $this->getJson('/api/v1/plantas/'.$plant->id.'?evento_sale=1');

        $response->assertOk();
        $response->assertJsonPath('precio_final', 130);
        $response->assertJsonPath('proyecto.descuento_defecto_cotizacion_web', 35);
    }

    public function test_it_keeps_global_price_base_order_across_pages(): void
    {
        $token = User::factory()->create()->createToken('plants-sorting-test')->plainTextToken;
        $headers = [
            'Authorization' => 'Bearer '.$token,
        ];

        $project = Proyecto::factory()->create([
            'is_active' => true,
        ]);

        $highestPricePlant = $this->createPlant($project->salesforce_id, true, [
            'name' => 'P3',
            'precio_base' => 300,
            'precio_lista' => 300,
        ]);

        $middlePricePlant = $this->createPlant($project->salesforce_id, true, [
            'name' => 'P2',
            'precio_base' => 200,
            'precio_lista' => 200,
        ]);

        $lowestPricePlant = $this->createPlant($project->salesforce_id, true, [
            'name' => 'P1',
            'precio_base' => 100,
            'precio_lista' => 100,
        ]);

        $pageOneResponse = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id.'&sort_by=price_base&sort_direction=desc&perPage=1&page=1', $headers);
        $pageOneResponse->assertOk();
        $pageOneResponse->assertJsonPath('data.0.id', $highestPricePlant->id);

        $pageTwoResponse = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id.'&sort_by=price_base&sort_direction=desc&perPage=1&page=2', $headers);
        $pageTwoResponse->assertOk();
        $pageTwoResponse->assertJsonPath('data.0.id', $middlePricePlant->id);

        $pageThreeResponse = $this->getJson('/api/v1/plantas?proyecto_id='.$project->id.'&sort_by=price_base&sort_direction=desc&perPage=1&page=3', $headers);
        $pageThreeResponse->assertOk();
        $pageThreeResponse->assertJsonPath('data.0.id', $lowestPricePlant->id);
    }

    private function createPlant(string $salesforceProyectoId, bool $isActive, array $attributes = []): Plant
    {
        return Plant::query()->create(array_merge([
            'salesforce_product_id' => (string) Str::uuid(),
            'salesforce_proyecto_id' => $salesforceProyectoId,
            'name' => strtoupper(substr((string) Str::uuid(), 0, 3)),
            'product_code' => 'PLANT-'.substr((string) Str::uuid(), 0, 8),
            'orientacion' => 'Norte',
            'programa' => '2 dormitorios',
            'programa2' => '2 baños',
            'precio_base' => 5000,
            'precio_lista' => 5500,
            'porcentaje_maximo_unidad' => 0,
            'is_active' => $isActive,
            'last_synced_at' => now(),
        ], $attributes));
    }
}
