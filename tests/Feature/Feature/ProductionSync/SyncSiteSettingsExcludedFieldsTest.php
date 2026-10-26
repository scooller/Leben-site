<?php

namespace Tests\Feature\Feature\ProductionSync;

use App\Models\SiteSetting;
use App\Services\ProductionSync\ProductionSyncProgressTracker;
use App\Services\ProductionSync\ProductionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncSiteSettingsExcludedFieldsTest extends TestCase
{
    use RefreshDatabase;

    private ProductionSyncService $service;

    private ProductionSyncProgressTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductionSyncService::class);
        $this->tracker = app(ProductionSyncProgressTracker::class);
    }

    /**
     * Helper: run syncSnapshot with only site_settings entity.
     */
    private function runSync(array $siteSettingsPayload, string $mode): void
    {
        $syncId = 'test-'.uniqid();
        $this->tracker->initialize($syncId, 10, 'http://localhost');

        $snapshot = ['site_settings' => $siteSettingsPayload];

        $this->service->syncSnapshot($syncId, $snapshot, $this->tracker, ['site_settings'], $mode);
    }

    public function test_update_mode_preserves_excluded_fields_in_extra_settings(): void
    {
        // Arrange: local settings have excluded fields configured
        $settings = SiteSetting::current();
        $settings->update([
            'extra_settings' => [
                'salesforce_sync_plants_excluded_fields' => ['precio_lista', 'is_active'],
                'salesforce_sync_projects_excluded_fields' => ['name', 'etapa'],
                'utm_source_default' => 'direct',
            ],
        ]);

        // Incoming payload from production (does not include salesforce_sync_* keys)
        $incomingPayload = [
            'site_name' => 'Produccion Site',
            'extra_settings' => [
                'utm_source_default' => 'google',
                // Note: no salesforce_sync_* keys — they are filtered by filterSyncableExtraSettings
            ],
        ];

        $this->runSync($incomingPayload, 'update');

        $settings->refresh();
        $extra = $settings->extra_settings;

        // Excluded fields must survive
        $this->assertSame(['precio_lista', 'is_active'], $extra['salesforce_sync_plants_excluded_fields'] ?? null,
            'salesforce_sync_plants_excluded_fields must be preserved in update mode');
        $this->assertSame(['name', 'etapa'], $extra['salesforce_sync_projects_excluded_fields'] ?? null,
            'salesforce_sync_projects_excluded_fields must be preserved in update mode');

        // Other fields from production are applied
        $this->assertSame('google', $extra['utm_source_default'] ?? null);
    }

    public function test_overwrite_mode_preserves_excluded_fields_in_extra_settings(): void
    {
        // Arrange: local settings have excluded fields configured
        $settings = SiteSetting::current();
        $settings->update([
            'extra_settings' => [
                'salesforce_sync_plants_excluded_fields' => ['precio_lista', 'is_active'],
                'salesforce_sync_projects_excluded_fields' => ['name'],
                'salesforce_sync_interval_minutes' => 60,
                'utm_source_default' => 'direct',
            ],
        ]);

        // Incoming payload from production (no salesforce_sync_* keys)
        $incomingPayload = [
            'site_name' => 'Produccion Site',
            'extra_settings' => [
                'utm_source_default' => 'google',
            ],
        ];

        $this->runSync($incomingPayload, 'overwrite');

        $settings->refresh();
        $extra = $settings->extra_settings;

        // All salesforce_sync_* keys must survive overwrite
        $this->assertSame(['precio_lista', 'is_active'], $extra['salesforce_sync_plants_excluded_fields'] ?? null,
            'salesforce_sync_plants_excluded_fields must be preserved in overwrite mode');
        $this->assertSame(['name'], $extra['salesforce_sync_projects_excluded_fields'] ?? null,
            'salesforce_sync_projects_excluded_fields must be preserved in overwrite mode');
        $this->assertSame(60, $extra['salesforce_sync_interval_minutes'] ?? null,
            'salesforce_sync_interval_minutes must be preserved in overwrite mode');

        // Fields from production are applied
        $this->assertSame('google', $extra['utm_source_default'] ?? null);
    }

    public function test_overwrite_mode_preserves_salesforce_oauth(): void
    {
        $settings = SiteSetting::current();
        $settings->update([
            'extra_settings' => [
                'salesforce_oauth' => ['token' => 'local-token-abc'],
                'salesforce_sync_plants_excluded_fields' => ['name'],
            ],
        ]);

        $incomingPayload = [
            'site_name' => 'Produccion Site',
            'extra_settings' => [
                'utm_source_default' => 'google',
            ],
        ];

        $this->runSync($incomingPayload, 'overwrite');

        $settings->refresh();
        $extra = $settings->extra_settings;

        $this->assertSame(['token' => 'local-token-abc'], $extra['salesforce_oauth'] ?? null,
            'salesforce_oauth must be preserved in overwrite mode');
        $this->assertSame(['name'], $extra['salesforce_sync_plants_excluded_fields'] ?? null,
            'salesforce_sync_plants_excluded_fields must be preserved in overwrite mode');
    }

    public function test_update_mode_preserves_excluded_fields_when_no_extra_settings_in_payload(): void
    {
        $settings = SiteSetting::current();
        $settings->update([
            'extra_settings' => [
                'salesforce_sync_plants_excluded_fields' => ['piso'],
            ],
        ]);

        // Payload with no extra_settings key at all
        $incomingPayload = ['site_name' => 'Produccion Site'];

        $this->runSync($incomingPayload, 'update');

        $settings->refresh();
        $extra = $settings->extra_settings;

        $this->assertSame(['piso'], $extra['salesforce_sync_plants_excluded_fields'] ?? null,
            'Excluded fields must survive when payload has no extra_settings');
    }
}
