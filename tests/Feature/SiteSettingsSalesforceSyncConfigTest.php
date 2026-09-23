<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingsSalesforceSyncConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_salesforce_sync_settings_in_site_settings(): void
    {
        SiteSetting::current()->update([
            'salesforce_sync_interval_minutes' => 120,
            'salesforce_sync_plant_types' => ['ESTACIONAMIENTO', 'BODEGA'],
        ]);

        $settings = SiteSetting::current()->fresh();

        $this->assertSame(120, $settings?->salesforce_sync_interval_minutes);
        $this->assertSame(['ESTACIONAMIENTO', 'BODEGA'], $settings?->salesforce_sync_plant_types);
    }

    public function test_it_sets_salesforce_sync_defaults_when_creating_settings_singleton(): void
    {
        $settings = SiteSetting::current();

        $this->assertSame(1440, $settings->salesforce_sync_interval_minutes);
        $this->assertSame(['ESTACIONAMIENTO', 'DEPARTAMENTO', 'BODEGA', 'LOCAL'], $settings->salesforce_sync_plant_types);
        $this->assertSame('300', $settings->qrOptions()['size']);
        $this->assertSame('square', $settings->qrOptions()['style']);
    }

    public function test_it_persists_excluded_fields_for_projects_and_plants_in_extra_settings(): void
    {
        SiteSetting::current()->update([
            'extra_settings' => [
                'salesforce_sync_projects_excluded_fields' => ['descripcion', 'telefono'],
                'salesforce_sync_plants_excluded_fields' => ['orientacion', 'precio_base'],
            ],
        ]);

        $settings = SiteSetting::current()->fresh();
        $extra = is_array($settings?->extra_settings) ? $settings->extra_settings : [];

        $this->assertSame(
            ['descripcion', 'telefono'],
            $extra['salesforce_sync_projects_excluded_fields'] ?? null,
        );
        $this->assertSame(
            ['orientacion', 'precio_base'],
            $extra['salesforce_sync_plants_excluded_fields'] ?? null,
        );
    }

    public function test_it_merges_global_qr_settings_with_package_defaults(): void
    {
        SiteSetting::current()->update([
            'extra_settings' => [
                'qr' => [
                    'size' => '420',
                    'style' => 'dot',
                    'color' => 'rgba(10, 20, 30, 1)',
                    'hasGradient' => true,
                    'gradient_form' => 'rgb(1, 2, 3)',
                    'gradient_to' => 'rgb(4, 5, 6)',
                ],
            ],
        ]);

        $options = SiteSetting::current()->fresh()->qrOptions();

        $this->assertSame('420', $options['size']);
        $this->assertSame('dot', $options['style']);
        $this->assertSame('rgba(10, 20, 30, 1)', $options['color']);
        $this->assertTrue($options['hasGradient']);
        $this->assertSame('H', $options['correction']);
        $this->assertSame('square', $options['eye_style']);
    }
}
