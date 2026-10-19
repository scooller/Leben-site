<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Plant;
use App\Models\PlantReservation;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CyberFeedApiTest extends TestCase
{
    use RefreshDatabase;

    private function createValidCyberPlant(array $plantAttributes = [], array $projectAttributes = [], ?Proyecto $proyecto = null): Plant
    {
        if (! $proyecto) {
            $proyecto = Proyecto::factory()->create(array_merge([
                'is_active' => true,
                'slug' => 'edificio-'.fake()->unique()->slug(2),
                'name' => 'Edificio '.fake()->words(2, true),
                'comuna' => 'Independencia',
                'provincia' => 'Santiago',
                'region' => 'Metropolitana',
                'entrega_inmediata' => true,
                'salesforce_portada_url' => 'https://admin.ileben.cl/storage/projects/indi.jpg',
                'valor_reserva_exigido_defecto_peso' => 100000,
            ], $projectAttributes));
        }

        return Plant::factory()->create(array_merge([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'is_active' => true,
            'unidad_sale' => true,
            'name' => (string) fake()->unique()->numberBetween(100, 999),
            'sku' => 'SKU-'.fake()->unique()->numberBetween(1000, 9999),
            'product_code' => '404 1D+1B',
            'programa' => '1D',
            'programa2' => '1B',
            'tipo_producto' => 'DEPARTAMENTO',
            'precio_lista' => 3000,
            'precio_base' => 2400,
            'descuento_defecto_cotizacion_web' => 20,
            'superficie_util' => 45.5,
            'salesforce_interior_image_url' => 'https://admin.ileben.cl/storage/plants/404.jpg',
        ], $plantAttributes));
    }

    public function test_feed_returns_200_and_canonical_structure(): void
    {
        $this->createValidCyberPlant([
            'name' => '404',
            'sku' => 'INDI-404',
        ], [
            'slug' => 'edificio-indi',
            'name' => 'Edificio Indi',
            'comuna' => 'Independencia',
        ]);

        $response = $this->getJson('/api/cyber/feed.json');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json; charset=utf-8');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=300', $cacheControl);
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertNotEmpty($response->headers->get('ETag'));

        $response->assertJsonStructure([
            'store' => [
                'name',
                'site',
                'currency',
                'updated_at',
            ],
            'meta' => [
                'total',
                'page',
                'per_page',
                'last_page',
            ],
            'offers' => [
                '*' => [
                    'id',
                    'sku',
                    'title',
                    'url',
                    'image',
                    'project',
                    'project_slug',
                    'commune',
                    'city',
                    'region',
                    'property_type',
                    'bedrooms',
                    'bathrooms',
                    'surface_m2',
                    'delivery_status',
                    'availability',
                    'reservation_price_clp',
                    'price_uf',
                    'reference_price_uf',
                    'discount_percent',
                    'valid_from',
                    'valid_until',
                    'updated_at',
                ],
            ],
        ]);

        $offer = $response->json('offers.0');
        $this->assertSame('INDI-404', $offer['sku']);
        $this->assertSame('Edificio Indi', $offer['project']);
        $this->assertSame('edificio-indi', $offer['project_slug']);
        $this->assertSame('Independencia', $offer['commune']);
        $this->assertSame('entrega_inmediata', $offer['delivery_status']);
        $this->assertSame('available', $offer['availability']);
        $this->assertSame(1, $offer['bedrooms']);
        $this->assertSame(1, $offer['bathrooms']);
        $this->assertEquals(45.5, $offer['surface_m2']);
        $this->assertGreaterThan(0, $offer['price_uf']);
        $this->assertEquals(3000, $offer['reference_price_uf']);
    }

    public function test_feed_filters_by_project(): void
    {
        $this->createValidCyberPlant([], ['slug' => 'edificio-indi', 'name' => 'Edificio Indi']);
        $this->createValidCyberPlant(['sku' => 'MOOD-501'], ['slug' => 'edificio-mood', 'name' => 'Edificio Mood']);

        $response = $this->getJson('/api/cyber/feed.json?project=edificio-indi');

        $response->assertOk();
        $offers = $response->json('offers');
        $this->assertCount(1, $offers);
        $this->assertSame('edificio-indi', $offers[0]['project_slug']);
    }

    public function test_feed_filters_by_commune(): void
    {
        $this->createValidCyberPlant([], ['comuna' => 'Independencia']);
        $this->createValidCyberPlant([], ['comuna' => 'Ñuñoa']);

        $response = $this->getJson('/api/cyber/feed.json?commune=independencia');

        $response->assertOk();
        $offers = $response->json('offers');
        $this->assertCount(1, $offers);
        $this->assertSame('Independencia', $offers[0]['commune']);
    }

    public function test_feed_handles_availability_states(): void
    {
        // 1. Available plant
        $availablePlant = $this->createValidCyberPlant(['sku' => 'AVAIL-101']);

        // 2. Reserved plant
        $reservedPlant = $this->createValidCyberPlant(['sku' => 'RES-102']);
        PlantReservation::create([
            'plant_id' => $reservedPlant->id,
            'session_token' => 'session-123',
            'status' => ReservationStatus::ACTIVE,
            'expires_at' => now()->addMinutes(15),
        ]);

        // 3. Sold plant (completed payment)
        $soldPlant = $this->createValidCyberPlant(['sku' => 'SOLD-103']);
        Payment::create([
            'plant_id' => $soldPlant->id,
            'gateway' => \App\Enums\PaymentGateway::TRANSBANK,
            'amount' => 100000,
            'status' => PaymentStatus::COMPLETED,
            'completed_at' => now(),
        ]);

        $response = $this->getJson('/api/cyber/feed.json');
        $response->assertOk();

        $offersBySku = collect($response->json('offers'))->keyBy('sku');

        $this->assertSame('available', $offersBySku['AVAIL-101']['availability']);
        $this->assertSame('reserved', $offersBySku['RES-102']['availability']);
        $this->assertSame('sold', $offersBySku['SOLD-103']['availability']);
    }

    public function test_feed_excludes_invalid_offers(): void
    {
        // Planta válida
        $this->createValidCyberPlant(['sku' => 'VALID-101']);

        // Planta sin imagen válida (debe ser excluida)
        $this->createValidCyberPlant([
            'sku' => 'NO-IMG-102',
            'salesforce_interior_image_url' => null,
            'cover_image_id' => null,
            'interior_image_id' => null,
        ], [
            'salesforce_portada_url' => null,
            'salesforce_logo_url' => null,
            'project_image_id' => null,
        ]);

        // Planta con precio 0 (debe ser excluida)
        $this->createValidCyberPlant([
            'sku' => 'ZERO-PRICE-103',
            'precio_lista' => 0,
            'precio_base' => 0,
        ]);

        $response = $this->getJson('/api/cyber/feed.json');
        $response->assertOk();

        $offers = $response->json('offers');
        $this->assertCount(1, $offers);
        $this->assertSame('VALID-101', $offers[0]['sku']);
    }

    public function test_feed_supports_etag_and_304_not_modified(): void
    {
        $this->createValidCyberPlant();

        $initialResponse = $this->getJson('/api/cyber/feed.json');
        $initialResponse->assertOk();
        $etag = $initialResponse->headers->get('ETag');

        $this->assertNotEmpty($etag);

        $conditionalResponse = $this->getJson('/api/cyber/feed.json', [
            'If-None-Match' => $etag,
        ]);

        $conditionalResponse->assertStatus(304);
    }

    public function test_artisan_command_generates_static_feed_file(): void
    {
        $this->createValidCyberPlant();

        $outputPath = storage_path('app/public/cyber/test_feed.json');
        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }

        $this->artisan('cyber:generate-feed', [
            '--output' => $outputPath,
            '--pretty' => true,
        ])->assertSuccessful();

        $this->assertFileExists($outputPath);

        $content = json_decode(File::get($outputPath), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('store', $content);
        $this->assertArrayHasKey('offers', $content);
        $this->assertNotEmpty($content['offers']);

        File::delete($outputPath);
    }

    public function test_generic_feed_endpoint_returns_200_and_same_structure(): void
    {
        $this->createValidCyberPlant();

        $responseJson = $this->getJson('/api/feed.json');
        $responseJson->assertOk();
        $responseJson->assertJsonStructure(['store', 'meta', 'offers']);

        $responseFeed = $this->getJson('/api/feed');
        $responseFeed->assertOk();
        $responseFeed->assertJsonStructure(['store', 'meta', 'offers']);
    }

    public function test_plant_automatically_generates_sku_on_creation(): void
    {
        $proyecto = Proyecto::factory()->create([
            'slug' => 'edificio-sol',
            'name' => 'Edificio Sol',
        ]);

        $plant = Plant::create([
            'salesforce_product_id' => 'sf-sol-101',
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'name' => '101',
            'product_code' => 'SOL-101',
            'is_active' => true,
        ]);

        $this->assertSame('SOL-101', $plant->fresh()->sku);
    }

    public function test_artisan_command_generates_skus_for_existing_plants(): void
    {
        $proyecto = Proyecto::factory()->create([
            'slug' => 'edificio-luna',
            'name' => 'Edificio Luna',
        ]);

        // Guardar sin disparar eventos para dejar el sku vacío
        $plant = new Plant([
            'salesforce_product_id' => 'sf-luna-201',
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'name' => '201',
            'product_code' => 'LUNA-201',
            'sku' => null,
            'is_active' => true,
        ]);
        $plant->saveQuietly();

        $this->assertNull($plant->fresh()->sku);

        $this->artisan('plants:generate-sku')
            ->assertSuccessful();

        $this->assertSame('LUNA-201', $plant->fresh()->sku);
    }

    public function test_artisan_command_actualizar_replaces_existing_skus(): void
    {
        $proyecto = Proyecto::factory()->create([
            'slug' => 'edificio-estrella',
            'name' => 'Edificio Estrella',
        ]);

        $plant = Plant::create([
            'salesforce_product_id' => 'sf-est-301',
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'name' => '301',
            'product_code' => 'EST-301',
            'sku' => 'OLD-SKU-999',
            'is_active' => true,
        ]);

        $this->assertSame('OLD-SKU-999', $plant->fresh()->sku);

        $this->artisan('plants:generate-sku', ['--actualizar' => true])
            ->assertSuccessful();

        $this->assertSame('ESTRELLA-301', $plant->fresh()->sku);
    }
}
