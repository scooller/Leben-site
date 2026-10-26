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

    public function test_contact_form_fields_schema_disables_duplicate_salesforce_fields(): void
    {
        $schema = \App\Filament\Pages\SiteSettings::getContactFormFieldsSchema();

        $salesforceField = collect($schema)->first(
            fn ($comp) => method_exists($comp, 'getName') && $comp->getName() === 'salesforce_field'
        );

        $this->assertNotNull($salesforceField);
        $this->assertInstanceOf(\Filament\Forms\Components\Select::class, $salesforceField);
        $this->assertTrue($salesforceField->isLive());
    }

    public function test_preload_global_form_binds_salesforce_payload_fields(): void
    {
        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre'],
                ['key' => 'rut', 'label' => 'RUT'],
                ['key' => 'phone', 'label' => 'Teléfono'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'message', 'label' => 'Mensaje'],
                ['key' => 'rango_renta', 'label' => 'Rango Renta'],
            ],
        ]);

        $schema = \Filament\Schemas\Schema::make();
        \App\Filament\Resources\ContactChannels\Schemas\ContactChannelForm::configure($schema);

        $section = collect($schema->getComponents())->first(
            fn ($c) => method_exists($c, 'getHeading') && $c->getHeading() === 'Configuración de formulario'
        );
        $this->assertNotNull($section);

        $action = collect($section->getHeaderActions())->first(
            fn ($a) => $a->getName() === 'preloadGlobalForm'
        );
        $this->assertNotNull($action);

        $assignedFields = null;
        $mockComponent = new class extends \Filament\Schemas\Components\Component {};
        $setCallback = new class($mockComponent, $assignedFields) extends \Filament\Schemas\Components\Utilities\Set
        {
            public function __construct(\Filament\Schemas\Components\Component $component, public &$assigned)
            {
                parent::__construct($component);
            }

            public function __invoke(string|\Filament\Schemas\Components\Component $path, mixed $state, bool $isAbsolute = false, bool $shouldCallUpdatedHooks = false): mixed
            {
                if ($path === 'form_fields') {
                    $this->assigned = $state;
                }

                return $state;
            }
        };

        $closure = $action->getActionFunction();
        $closure($setCallback);

        $this->assertIsArray($assignedFields);
        $this->assertCount(6, $assignedFields);

        $fields = array_values($assignedFields);
        $this->assertSame('FirstName', $fields[0]['salesforce_field']);
        $this->assertSame('RUT__c', $fields[1]['salesforce_field']);
        $this->assertSame('Phone', $fields[2]['salesforce_field']);
        $this->assertSame('Email', $fields[3]['salesforce_field']);
        $this->assertSame('Comentario_Cliente__c', $fields[4]['salesforce_field']);
        $this->assertSame('Rango_de_renta_liquida__c', $fields[5]['salesforce_field']);
    }
}
