<?php

namespace Tests\Unit\Services;

use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Services\ProductionSync\ProductionSyncProgressTracker;
use App\Services\ProductionSync\ProductionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProductionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_snapshot_logs_warning_when_required_config_is_missing(): void
    {
        config()->set('services.production_sync.base_url', '');
        config()->set('services.production_sync.token', '');

        Log::spy();

        $snapshot = app(ProductionSyncService::class)->fetchSnapshot();

        $this->assertSame('Falta configurar PRODUCTION_SYNC_BASE_URL o PRODUCTION_SYNC_TOKEN.', $snapshot['meta']['error']);

        Log::shouldHaveReceived('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context): bool {
                return $level === 'warning'
                    && $message === 'ProductionSync: Configuración incompleta para export de snapshot'
                    && $context['base_url_configured'] === false
                    && $context['token_configured'] === false;
            });
    }

    public function test_fetch_snapshot_uses_bearer_token_and_authorized_url_header(): void
    {
        config()->set('services.production_sync.base_url', 'https://prod.ileben.cl');
        config()->set('services.production_sync.token', 'test-token');
        config()->set('services.production_sync.authorized_url', 'https://dev.ileben.cl');
        config()->set('services.production_sync.timeout', 30);

        Http::fake([
            'https://prod.ileben.cl/api/v1/production-sync/export' => Http::response([
                'meta' => ['app_env' => 'production'],
                'site_settings' => ['site_name' => 'Prod'],
                'projects' => [],
                'plants' => [],
            ]),
        ]);

        $snapshot = app(ProductionSyncService::class)->fetchSnapshot();

        $this->assertSame('production', $snapshot['meta']['app_env']);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://prod.ileben.cl/api/v1/production-sync/export'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request->hasHeader('X-Authorized-Url', 'https://dev.ileben.cl');
        });
    }

    public function test_sync_snapshot_updates_site_settings_and_upserts_projects_and_plants(): void
    {
        SiteSetting::current()->update([
            'site_name' => 'Local',
            'extra_settings' => [
                'keep_local' => 'yes',
            ],
        ]);

        Proyecto::factory()->create([
            'salesforce_id' => 'SF-PROJ-1',
            'name' => 'Proyecto Local',
        ]);

        Plant::factory()->create([
            'salesforce_product_id' => 'SF-PLANT-1',
            'salesforce_proyecto_id' => 'SF-PROJ-1',
            'name' => 'Planta Local',
        ]);

        $snapshot = [
            'site_settings' => [
                'site_name' => 'Prod Site',
                'mostrar_plantas' => true,
                'extra_settings' => [
                    'public_value' => 'ok',
                    'hero_url' => 'https://blocked.example.com',
                ],
            ],
            'projects' => [
                [
                    'salesforce_id' => 'SF-PROJ-1',
                    'name' => 'Proyecto Actualizado',
                    'is_active' => true,
                ],
                [
                    'salesforce_id' => 'SF-PROJ-2',
                    'name' => 'Proyecto Nuevo',
                    'is_active' => true,
                ],
            ],
            'plants' => [
                [
                    'salesforce_product_id' => 'SF-PLANT-1',
                    'salesforce_proyecto_id' => 'SF-PROJ-1',
                    'name' => 'Planta Actualizada',
                    'is_active' => true,
                ],
                [
                    'salesforce_product_id' => 'SF-PLANT-2',
                    'salesforce_proyecto_id' => 'SF-PROJ-2',
                    'name' => 'Planta Nueva',
                    'is_active' => true,
                ],
            ],
        ];

        $service = app(ProductionSyncService::class);
        $tracker = app(ProductionSyncProgressTracker::class);
        $syncId = 'sync-test-1';
        $tracker->initialize($syncId, 5, 'https://prod.ileben.cl');

        $result = $service->syncSnapshot($syncId, $snapshot, $tracker);

        $this->assertSame('updated', $result['site_settings']);
        $this->assertSame(1, $result['projects']['created']);
        $this->assertSame(1, $result['projects']['updated']);
        $this->assertSame(1, $result['plants']['created']);
        $this->assertSame(1, $result['plants']['updated']);

        $this->assertDatabaseHas('proyectos', [
            'salesforce_id' => 'SF-PROJ-1',
            'name' => 'Proyecto Actualizado',
        ]);

        $this->assertDatabaseHas('proyectos', [
            'salesforce_id' => 'SF-PROJ-2',
            'name' => 'Proyecto Nuevo',
        ]);

        $this->assertDatabaseHas('plants', [
            'salesforce_product_id' => 'SF-PLANT-1',
            'name' => 'Planta Actualizada',
        ]);

        $this->assertDatabaseHas('plants', [
            'salesforce_product_id' => 'SF-PLANT-2',
            'name' => 'Planta Nueva',
        ]);

        $settings = SiteSetting::current()->fresh();
        $extra = is_array($settings->extra_settings) ? $settings->extra_settings : [];

        $this->assertSame('Prod Site', $settings->site_name);
        $this->assertSame('yes', $extra['keep_local'] ?? null);
        $this->assertSame('ok', $extra['public_value'] ?? null);
        $this->assertArrayNotHasKey('hero_url', $extra);
    }

    public function test_fetch_snapshot_logs_warning_for_non_success_http_response(): void
    {
        config()->set('services.production_sync.base_url', 'https://prod.ileben.cl');
        config()->set('services.production_sync.token', 'test-token');

        Http::fake([
            'https://prod.ileben.cl/api/v1/production-sync/export' => Http::response([
                'message' => 'Access denied',
            ], 403),
        ]);

        Log::spy();

        $snapshot = app(ProductionSyncService::class)->fetchSnapshot();

        $this->assertSame('Access denied', $snapshot['meta']['error']);

        Log::shouldHaveReceived('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context): bool {
                return $level === 'warning'
                    && $message === 'ProductionSync: Respuesta no exitosa al obtener snapshot'
                    && $context['http_status'] === 403
                    && $context['response_message'] === 'Access denied';
            });
    }

    public function test_fetch_snapshot_logs_error_and_returns_fallback_on_connection_exception(): void
    {
        config()->set('services.production_sync.base_url', 'https://prod.ileben.cl');
        config()->set('services.production_sync.token', 'test-token');

        Http::fake(function (): void {
            throw new ConnectionException('Connection failed');
        });

        Log::spy();

        $snapshot = app(ProductionSyncService::class)->fetchSnapshot();

        $this->assertSame('No se pudo obtener la sincronización de producción.', $snapshot['meta']['error']);

        Log::shouldHaveReceived('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context): bool {
                return $level === 'error'
                    && $message === 'ProductionSync: Error de red al obtener snapshot'
                    && str_contains((string) $context['endpoint'], '/api/v1/production-sync/export')
                    && ($context['exception_message'] ?? null) === 'Connection failed';
            });
    }
}
