<?php

namespace Tests\Unit\Salesforce;

use App\Models\Asesor;
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
        $this->assertSame('iLeben', $payload['Company'] ?? null);
        $this->assertSame('992285134', $payload['Phone'] ?? null);
        $this->assertSame('992285134', $payload['MobilePhone'] ?? null);
        $this->assertSame('alejandro@example.com', $payload['Email'] ?? null);
        $this->assertSame('leben.cl', $payload['Website'] ?? null);
        $this->assertSame('alejandro@example.com', $payload['Email__c'] ?? null);
        $this->assertSame('11.455.798-6', $payload['RUT__c'] ?? null);
        $this->assertSame('Direct', $payload['LeadSource'] ?? null);
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
        $this->assertSame('Direct', $payload['Medio_de_Llegada__c'] ?? null);
        $this->assertSame('BlackFriday', $payload['Nombre_de_la_Campa_a__c'] ?? null);
        $this->assertSame('organic', $payload['Audiencia__c'] ?? null);
        $this->assertSame('AON_Mood_anuncio_5', $payload['Pieza_Grafica__c'] ?? null);
        $this->assertSame('+56942221542', $payload['wsp_owner__c'] ?? null);
        $this->assertSame('+56942221542', $payload['Telefono_owner__c'] ?? null);
        $this->assertSame('+56942221542', $payload['owner_phone__c'] ?? null);
        $this->assertSame('992285134', $payload['whatsapp_phone__c'] ?? null);
        $this->assertSame('https://wa.me/992285134?text=Hola%20ALEJANDRO%2C%20te%20contacto%20desde%20Leben.%20%C2%BFTienes%20un%20minuto%3F', $payload['Whatsapp_Link__c'] ?? null);
        $this->assertSame('<a href="https://wa.me/992285134?text=Hola%20ALEJANDRO%2C%20te%20contacto%20desde%20Leben.%20%C2%BFTienes%20un%20minuto%3F" target="_blank">Link</a>', $payload['Whatsapp_Link_URL__c'] ?? null);
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
}
