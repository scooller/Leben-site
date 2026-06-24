<?php

namespace Tests\Unit\Salesforce;

use App\Models\Asesor;
use App\Models\ContactChannel;
use App\Models\ContactSubmission;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Services\Salesforce\SalesforceCaseMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesforceCaseMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_contact_submission_into_salesforce_lead_payload(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');
        config()->set('services.salesforce.whatsapp_phone', '56989011686');
        config()->set('services.salesforce.whatsapp_owner_name', 'ANDREA');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_email' => 'inscripciones@ileben.cl',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
                ['key' => 'arrival_channel', 'label' => 'Medio de llegada', 'type' => 'text', 'required' => false],
                ['key' => 'rango', 'label' => 'Rango de renta', 'type' => 'select', 'required' => false],
                ['key' => 'codeudor', 'label' => 'Complementa renta', 'type' => 'select', 'required' => false],
                ['key' => 'validacion_renta', 'label' => 'Validación renta', 'type' => 'select', 'required' => false],
                ['key' => 'buscas', 'label' => 'Uso departamento', 'type' => 'select', 'required' => false],
                ['key' => 'elaboral', 'label' => 'Estado laboral', 'type' => 'select', 'required' => false],
                ['key' => 'comuna_inversion', 'label' => 'Comuna inversión', 'type' => 'text', 'required' => false],
            ],
        ]);

        $project = Proyecto::query()->create([
            'salesforce_id' => 'a0J8c00000sdxCXEAY',
            'name' => 'Edificio Indigo',
            'slug' => 'edificio-indigo',
            'is_active' => true,
        ]);

        $advisor = Asesor::query()->create([
            'first_name' => 'Andrea',
            'last_name' => 'Leben',
            'email' => 'andrea@example.com',
            'whatsapp_owner' => '+56942221542',
            'is_active' => true,
        ]);

        $project->asesores()->attach($advisor->id);

        $submission = ContactSubmission::query()->create([
            'name' => 'Alejandro',
            'email' => 'alejandro@example.com',
            'phone' => '992285134',
            'rut' => '11.455.798-6',
            'fields' => [
                'name' => 'Alejandro',
                'lastname' => 'Reveco',
                'project_name' => 'Edificio Indigo',
                'comuna' => 'Puerto Varas',
                'arrival_channel' => 'BlackInmobiliario',
                'medio' => 'meta',
                'rango' => 'Entre $2.500.000 y $3.500.000',
                'codeudor' => 'no, no puedo complementarla.',
                'validacion_renta' => 'Aprobada con observaciones',
                'buscas' => 'Inversión para arriendo',
                'elaboral' => 'Dependiente con antigüedad',
                'comuna_inversion' => 'Ñuñoa',
                'utm_source' => 'direct',
                'utm_site' => 'leben.cl',
                'utm_medium' => 'organic',
                'utm_campaign' => 'BlackFriday',
                'utm_content' => 'AON_Mood_anuncio_5',
                'utm_term' => 'clientes-potenciales',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('Alejandro', $payload['FirstName'] ?? null);
        $this->assertSame('Reveco', $payload['LastName'] ?? null);
        $this->assertSame('', $payload['Company'] ?? null);
        $this->assertSame('992285134', $payload['Phone'] ?? null);
        $this->assertSame('992285134', $payload['MobilePhone'] ?? null);
        $this->assertSame('alejandro@example.com', $payload['Email'] ?? null);
        $this->assertSame('leben.cl', $payload['Website'] ?? null);
        $this->assertSame('alejandro@example.com', $payload['Email__c'] ?? null);
        $this->assertSame('11.455.798-6', $payload['RUT__c'] ?? null);
        $this->assertSame('Clientes-potenciales', $payload['LeadSource'] ?? null);
        $this->assertSame('En Contacto', $payload['Status'] ?? null);
        $this->assertSame('005U100000CAG4bIAH', $payload['OwnerId'] ?? null);
        $this->assertSame('Online', $payload['Tipo_Ingreso__c'] ?? null);
        $this->assertSame('a0J8c00000sdxCXEAY', $payload['Proyecto__c'] ?? null);
        $this->assertSame('a0J8c00000sdxCXEAY', $payload['ID_Proyecto__c'] ?? null);
        $this->assertSame('Edificio_Indigo', $payload['Informacion_Cotizacion__c'] ?? null);
        $this->assertSame('Edificio_Indigo', $payload['Proyect_ID__c'] ?? null);
        $this->assertSame('Puerto_Varas', $payload['Comuna__c'] ?? null);
        $this->assertSame('Entre $2.500.000 y $3.500.000', $payload['Rango_de_renta_liquida__c'] ?? null);
        $this->assertSame('no, no puedo complementarla.', $payload['complementaRenta__c'] ?? null);
        $this->assertSame('Aprobada con observaciones', $payload['Validaci_n_Renta__c'] ?? null);
        $this->assertSame('Inversión para arriendo', $payload['usoDepartamento__c'] ?? null);
        $this->assertSame('Dependiente con antigüedad', $payload['estadoLaboral__c'] ?? null);
        $this->assertSame('Ñuñoa', $payload['comunaInversion__c'] ?? null);
        $this->assertSame('direct', $payload['Medio_de_Llegada__c'] ?? null);
        $this->assertSame('BlackFriday', $payload['Nombre_de_la_Campa_a__c'] ?? null);
        $this->assertSame('organic', $payload['Audiencia__c'] ?? null);
        $this->assertSame('AON_Mood_anuncio_5', $payload['Pieza_Grafica__c'] ?? null);
        $this->assertSame('+56942221542', $payload['wsp_owner__c'] ?? null);
        $this->assertSame('+56942221542', $payload['Telefono_owner__c'] ?? null);
        $this->assertSame('+56942221542', $payload['owner_phone__c'] ?? null);
        $this->assertArrayNotHasKey('whatsapp_phone__c', $payload);
        $this->assertArrayNotHasKey('Whatsapp_Link__c', $payload);
        $this->assertArrayNotHasKey('Whatsapp_Link_URL__c', $payload);
        $this->assertSame('direct', $payload['utm_source__c'] ?? null);
        $this->assertSame('organic', $payload['utm_medium__c'] ?? null);
        $this->assertSame('BlackFriday', $payload['utm_campaign__c'] ?? null);
        $this->assertSame('AON_Mood_anuncio_5', $payload['utm_content__c'] ?? null);
        $this->assertSame('clientes-potenciales', $payload['utm_term__c'] ?? null);
        $this->assertStringContainsString('Nombre: Alejandro', $payload['Description'] ?? '');
        $this->assertStringContainsString('Proyecto: Edificio Indigo', $payload['Description'] ?? '');
        $this->assertStringContainsString('UTM Source: direct', $payload['Description'] ?? '');
    }

    public function test_it_falls_back_to_main_commune_when_investment_commune_is_missing(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
                ['key' => 'comuna', 'label' => 'Comuna', 'type' => 'text', 'required' => false],
            ],
        ]);

        Proyecto::query()->create([
            'salesforce_id' => 'a0J8c00000sdxDDEAY',
            'name' => 'Edificio Fallback',
            'slug' => 'edificio-fallback',
            'is_active' => true,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Cesar',
            'email' => 'cesar@example.com',
            'phone' => '321654987',
            'rut' => '11.111.111-1',
            'fields' => [
                'name' => 'Cesar',
                'lastname' => 'Test',
                'project_name' => 'Edificio Fallback',
                'comuna' => 'Ñuñoa',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('Ñuñoa', $payload['comunaInversion__c'] ?? null);
    }

    public function test_it_uses_site_setting_default_for_campaign_when_utm_campaign_is_missing(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'extra_settings' => [
                'utm_campaign_default' => 'campaign',
            ],
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
            ],
        ]);

        Proyecto::query()->create([
            'salesforce_id' => 'a0J8c00000sdxEEEAY',
            'name' => 'Edificio Campaign',
            'slug' => 'edificio-campaign',
            'is_active' => true,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Cesar',
            'email' => 'cesar@example.com',
            'phone' => '321654987',
            'rut' => '11.111.111-1',
            'fields' => [
                'name' => 'Cesar',
                'lastname' => 'Test',
                'project_name' => 'Edificio Campaign',
                'utm_source' => 'direct',
                'utm_medium' => 'organic',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('direct', $payload['Website'] ?? null);
        $this->assertSame('campaign', $payload['Nombre_de_la_Campa_a__c'] ?? null);
        $this->assertSame('campaign', $payload['utm_campaign__c'] ?? null);
    }

    public function test_it_uses_site_setting_defaults_for_missing_utm_fields(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'extra_settings' => [
                'utm_source_default' => 'direct',
                'utm_medium_default' => 'organic',
                'utm_campaign_default' => 'campaign',
                'utm_term_default' => 'none',
                'utm_content_default' => 'none',
                'utm_site_default' => 'demo.ileben.cl',
            ],
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
            ],
        ]);

        Proyecto::query()->create([
            'salesforce_id' => 'a0J8c00000sdxZZEAY',
            'name' => 'Edificio Defaults',
            'slug' => 'edificio-defaults',
            'is_active' => true,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Default User',
            'email' => 'default.user@example.com',
            'phone' => '56911112222',
            'rut' => '11.111.111-1',
            'fields' => [
                'name' => 'Default User',
                'lastname' => 'Test',
                'project_name' => 'Edificio Defaults',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('direct', $payload['utm_source__c'] ?? null);
        $this->assertSame('organic', $payload['utm_medium__c'] ?? null);
        $this->assertSame('campaign', $payload['utm_campaign__c'] ?? null);
        $this->assertSame('none', $payload['utm_term__c'] ?? null);
        $this->assertSame('none', $payload['utm_content__c'] ?? null);
        $this->assertSame('demo.ileben.cl', $payload['Website'] ?? null);
    }

    public function test_it_preserves_explicit_utm_values_over_site_setting_defaults(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'extra_settings' => [
                'utm_source_default' => 'direct',
                'utm_medium_default' => 'organic',
                'utm_campaign_default' => 'campaign',
                'utm_term_default' => 'none',
                'utm_content_default' => 'none',
                'utm_site_default' => 'demo.ileben.cl',
            ],
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
            ],
        ]);

        Proyecto::query()->create([
            'salesforce_id' => 'a0J8c00000sdxYYEAY',
            'name' => 'Edificio Explicit',
            'slug' => 'edificio-explicit',
            'is_active' => true,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Explicit User',
            'email' => 'explicit.user@example.com',
            'phone' => '56933334444',
            'rut' => '11.111.111-1',
            'fields' => [
                'name' => 'Explicit User',
                'lastname' => 'Test',
                'project_name' => 'Edificio Explicit',
                'utm_source' => 'facebook',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'campana-test',
                'utm_term' => 'keyword-test',
                'utm_content' => 'ad-test',
                'utm_site' => 'landing.ileben.cl',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('facebook', $payload['utm_source__c'] ?? null);
        $this->assertSame('cpc', $payload['utm_medium__c'] ?? null);
        $this->assertSame('campaign', $payload['utm_campaign__c'] ?? null);
        $this->assertSame('keyword-test', $payload['utm_term__c'] ?? null);
        $this->assertSame('ad-test', $payload['utm_content__c'] ?? null);
        $this->assertSame('landing.ileben.cl', $payload['Website'] ?? null);
    }

    public function test_it_always_uses_site_setting_default_campaign_when_configured(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'extra_settings' => [
                'utm_campaign_default' => 'BlackInmobiliario',
            ],
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
            ],
        ]);

        Proyecto::query()->create([
            'salesforce_id' => 'a0J8c00000sdxFFEAY',
            'name' => 'Edificio Auto Tagging',
            'slug' => 'edificio-auto-tagging',
            'is_active' => true,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'phone' => '123456789',
            'rut' => '11.111.111-1',
            'fields' => [
                'name' => 'Test',
                'lastname' => 'Nuevo',
                'project_name' => 'Edificio Auto Tagging',
                'utm_source' => 'direct',
                'utm_medium' => 'organic',
                'utm_campaign' => 'otra-campana',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('BlackInmobiliario', $payload['Nombre_de_la_Campa_a__c'] ?? null);
        $this->assertSame('BlackInmobiliario', $payload['utm_campaign__c'] ?? null);
    }

    public function test_it_omits_description_when_disabled_in_site_settings(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'extra_settings' => [
                'salesforce_include_description' => false,
            ],
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
            ],
        ]);

        Proyecto::query()->create([
            'salesforce_id' => 'a0J8c00000sdxGGEAY',
            'name' => 'Edificio Description Off',
            'slug' => 'edificio-description-off',
            'is_active' => true,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Cesar',
            'email' => 'cesar@example.com',
            'phone' => '321654987',
            'rut' => '11.111.111-1',
            'fields' => [
                'name' => 'Cesar',
                'lastname' => 'Test',
                'project_name' => 'Edificio Description Off',
                'comuna' => 'Ñuñoa',
                'utm_source' => 'direct',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertArrayNotHasKey('Description', $payload);
    }

    public function test_it_falls_back_to_case_owner_when_lead_owner_id_is_invalid(): void
    {
        config()->set('services.salesforce.lead_owner_id', 'owner-invalido');
        config()->set('services.salesforce.case_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
            ],
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Mariana Test',
            'email' => 'mariana@example.com',
            'phone' => '987654321',
            'fields' => [
                'name' => 'Mariana Test',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('005U100000CAG4bIAH', $payload['OwnerId'] ?? null);
    }

    public function test_it_maps_lead_source_from_utm_term(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'origen_prospecto', 'label' => 'Origen del prospecto', 'type' => 'text', 'required' => false],
            ],
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Cesar Test',
            'email' => 'cesar.test@example.com',
            'phone' => '56911111111',
            'fields' => [
                'name' => 'Cesar Test',
                'apellido' => 'Mapper',
                'origen_prospecto' => 'facebook ads',
                'medio_de_llegada' => 'Meta',
                'utm_term' => 'trafico-meta',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('Trafico-meta', $payload['LeadSource'] ?? null);
        $this->assertSame('Meta', $payload['Medio_de_Llegada__c'] ?? null);
        $this->assertSame('Meta', $payload['utm_source__c'] ?? null);
    }

    public function test_it_uses_utm_term_over_origen_prospecto_for_lead_source(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'origen_prospecto', 'label' => 'Origen del prospecto', 'type' => 'text', 'required' => false],
                ['key' => 'medio_de_llegada', 'label' => 'Medio de llegada', 'type' => 'text', 'required' => false],
            ],
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Cesar Test',
            'email' => 'cesar.test@example.com',
            'phone' => '56911111111',
            'fields' => [
                'name' => 'Cesar Test',
                'origen_prospecto' => 'Leben | Vive el sur | Edificio Inn | ICON | Brochure | Febrero 2026',
                'medio_de_llegada' => 'Meta',
                'utm_source' => 'Meta',
                'utm_term' => 'campana-busqueda-2026',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('Campana-busqueda-2026', $payload['LeadSource'] ?? null);
        $this->assertSame('Meta', $payload['Medio_de_Llegada__c'] ?? null);
        $this->assertSame('Meta', $payload['utm_source__c'] ?? null);
    }

    public function test_it_uses_channel_domain_for_website_when_available(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
            ],
        ]);

        $channel = ContactChannel::query()->create([
            'slug' => 'sale-web-website-test',
            'name' => 'Sale Web Website Test',
            'is_active' => true,
            'is_default' => false,
            'domain_patterns' => ['sale.ileben.cl', '*.sale.ileben.cl'],
        ]);

        $submission = ContactSubmission::query()->create([
            'contact_channel_id' => $channel->id,
            'name' => 'Cesar Test',
            'email' => 'cesar.test@example.com',
            'phone' => '56911111111',
            'fields' => [
                'name' => 'Cesar Test',
                'project_name' => 'Edificio Inn',
                'utm_site' => 'meta.ileben.cl',
                'utm_source' => 'Meta',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission->load('channel'));

        $this->assertSame('sale.ileben.cl', $payload['Website'] ?? null);
    }

    public function test_it_prefers_informacion_cotizacion_field_over_project_name_for_salesforce_field(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
                ['key' => 'informacion_cotizacion', 'label' => 'Informacion Cotizacion', 'type' => 'text', 'required' => false],
            ],
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Cesar Test',
            'email' => 'cesar.test@example.com',
            'phone' => '56911111111',
            'fields' => [
                'name' => 'Cesar Test',
                'project_name' => 'Edificio Baum',
                'informacion_cotizacion' => 'Se cotizo el Departamento 1902 desde Portal Inmobiliario',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('Se_cotizo_el_Departamento_1902_desde_Portal_Inmobiliario', $payload['Informacion_Cotizacion__c'] ?? null);
    }

    public function test_it_uses_medio_de_llegada_for_website_when_explicit_site_is_missing(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
            ],
            'extra_settings' => [
                'utm_site_default' => null,
            ],
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Cesar Test',
            'email' => 'cesar.test@example.com',
            'phone' => '56911111111',
            'fields' => [
                'name' => 'Cesar Test',
                'medio_de_llegada' => 'Portal Inmobiliario',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('Portal Inmobiliario', $payload['Website'] ?? null);
    }

    public function test_it_prefers_imported_source_arrival_and_campaign_over_site_defaults(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'extra_settings' => [
                'utm_source_default' => 'direct',
                'utm_campaign_default' => 'CyberDay',
            ],
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
            ],
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Import User',
            'email' => 'import.user@example.com',
            'phone' => '56911111111',
            'user_agent' => 'filament-csv-import',
            'fields' => [
                'name' => 'Import User',
                'medio_de_llegada' => 'Portal Inmobiliario',
                'origen_del_prospecto' => 'Portal Inmobiliario',
                'campana' => 'Portal Inmobiliario',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('Portal Inmobiliario', $payload['utm_source__c'] ?? null);
        $this->assertSame('Portal Inmobiliario', $payload['LeadSource'] ?? null);
        $this->assertSame('Portal_Inmobiliario', $payload['Medio_de_Llegada__c'] ?? null);
        $this->assertSame('Portal Inmobiliario', $payload['utm_medium__c'] ?? null);
        $this->assertSame('Portal_Inmobiliario', $payload['Nombre_de_la_Campa_a__c'] ?? null);
        $this->assertSame('Portal Inmobiliario', $payload['utm_campaign__c'] ?? null);
    }

    public function test_it_resolves_project_with_case_insensitive_name(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
            ],
        ]);

        Proyecto::query()->create([
            'salesforce_id' => 'a0J8c00000CaseIA',
            'name' => 'Edificio Indigo',
            'slug' => 'edificio-indigo',
            'is_active' => true,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '56911112222',
            'fields' => [
                'name' => 'Test User',
                'project_name' => 'edificio indigo',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('a0J8c00000CaseIA', $payload['Proyecto__c'] ?? null);
        $this->assertSame('a0J8c00000CaseIA', $payload['ID_Proyecto__c'] ?? null);
    }

    public function test_it_resolves_project_with_accented_name_via_signature_match(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
            ],
        ]);

        Proyecto::query()->create([
            'salesforce_id' => 'a0J8c00000AccntA',
            'name' => 'Edificio Índigo',
            'slug' => 'edificio-indigo',
            'is_active' => true,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '56911112222',
            'fields' => [
                'name' => 'Test User',
                'project_name' => 'Edificio Indigo',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('a0J8c00000AccntA', $payload['Proyecto__c'] ?? null);
        $this->assertSame('a0J8c00000AccntA', $payload['ID_Proyecto__c'] ?? null);
    }

    public function test_it_resolves_project_via_slug_fallback(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'proyecto', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
            ],
        ]);

        Proyecto::query()->create([
            'salesforce_id' => 'a0J8c00000SlugMA',
            'name' => 'Edificio Alameda',
            'slug' => 'edificio_alameda',
            'is_active' => true,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '56911112222',
            'fields' => [
                'name' => 'Test User',
                'proyecto' => 'Edificio Alameda',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('a0J8c00000SlugMA', $payload['Proyecto__c'] ?? null);
    }

    public function test_it_resolves_project_using_project_alias(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
            ],
        ]);

        Proyecto::query()->create([
            'salesforce_id' => 'a0J8c00000PrjctA',
            'name' => 'Edificio Residencia',
            'slug' => 'edificio-residencia',
            'is_active' => true,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '56911112222',
            'fields' => [
                'name' => 'Test User',
                'project' => 'Edificio Residencia',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('a0J8c00000PrjctA', $payload['Proyecto__c'] ?? null);
        $this->assertSame('a0J8c00000PrjctA', $payload['ID_Proyecto__c'] ?? null);
    }

    public function test_it_omits_project_fields_when_project_not_found(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
            ],
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '56911112222',
            'fields' => [
                'name' => 'Test User',
                'project_name' => 'Proyecto Inexistente',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertArrayNotHasKey('Proyecto__c', $payload);
        $this->assertArrayNotHasKey('ID_Proyecto__c', $payload);
    }

    public function test_it_prefers_direct_proyecto_salesforce_id_over_name_lookup(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
            ],
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '56911112222',
            'fields' => [
                'name' => 'Test User',
                'proyecto_salesforce_id' => 'a0J8c00000DirXX',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('a0J8c00000DirXX', $payload['Proyecto__c'] ?? null);
        $this->assertSame('a0J8c00000DirXX', $payload['ID_Proyecto__c'] ?? null);
    }

    public function test_it_resolves_project_name_from_model_when_field_contains_salesforce_id(): void
    {
        config()->set('services.salesforce.lead_owner_id', '005U100000CAG4bIAH');
        config()->set('services.salesforce.lead_status', 'En Contacto');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
            ],
        ]);

        Proyecto::query()->create([
            'salesforce_id' => 'a0J8c00000IdAsName',
            'name' => 'Edificio Aconcagua',
            'slug' => 'edificio-aconcagua',
            'is_active' => true,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '56911112222',
            'fields' => [
                'name' => 'Test User',
                'project_name' => 'a0J8c00000IdAsName',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->mapLead($submission);

        $this->assertSame('a0J8c00000IdAsName', $payload['Proyecto__c'] ?? null);
        $this->assertSame('a0J8c00000IdAsName', $payload['ID_Proyecto__c'] ?? null);
        $this->assertSame('Edificio_Aconcagua', $payload['Informacion_Cotizacion__c'] ?? null);
        $this->assertSame('Edificio_Aconcagua', $payload['Proyect_ID__c'] ?? null);
    }
}
