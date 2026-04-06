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

        Sanctum::actingAs(User::factory()->create());
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
            'is_active' => true,
        ]);

        $projectInProvidencia = Proyecto::factory()->create([
            'comuna' => 'Providencia',
            'region' => 'Metropolitana',
            'is_active' => true,
        ]);

        $this->createPlant($projectInSantiago->salesforce_id, true);
        $this->createPlant($projectInProvidencia->salesforce_id, true);

        $response = $this->getJson('/api/v1/plantas/filtros-ubicacion');

        $response->assertOk();
        $response->assertJsonFragment(['regions' => ['Metropolitana']]);
        $response->assertJsonFragment(['comunas' => ['Providencia', 'Santiago']]);
        $response->assertJsonPath('comunas_by_region.Metropolitana.0', 'Providencia');
        $response->assertJsonPath('comunas_by_region.Metropolitana.1', 'Santiago');
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

        $advisor = Asesor::factory()->create([
            'first_name' => 'Camila',
            'last_name' => 'Diaz',
            'email' => 'camila@example.com',
            'whatsapp_owner' => '+56 9 8765 4321',
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
        $response->assertJsonPath('proyecto.asesores.0.avatar_url', $logoMedia->url);
    }

    private function createPlant(string $salesforceProyectoId, bool $isActive): Plant
    {
        return Plant::query()->create([
            'salesforce_product_id' => (string) Str::uuid(),
            'salesforce_proyecto_id' => $salesforceProyectoId,
            'name' => strtoupper(substr((string) Str::uuid(), 0, 3)),
            'product_code' => 'PLANT-'.substr((string) Str::uuid(), 0, 8),
            'programa' => '2 dormitorios',
            'programa2' => '2 baños',
            'precio_base' => 5000,
            'precio_lista' => 5500,
            'is_active' => $isActive,
            'last_synced_at' => now(),
        ]);
    }
}
