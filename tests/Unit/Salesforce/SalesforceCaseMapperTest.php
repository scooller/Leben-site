<?php

namespace Tests\Unit\Salesforce;

use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use App\Services\Salesforce\SalesforceCaseMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesforceCaseMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_contact_submission_into_salesforce_case_payload(): void
    {
        config()->set('services.salesforce.case_record_type_id', '0128c000002wPrTAAU');
        config()->set('services.salesforce.case_owner_id', '005U1000001DCCZIA4');
        config()->set('services.salesforce.case_source_id', '02sU100000aMcxzIAC');
        config()->set('services.salesforce.case_status', 'Nuevo');
        config()->set('services.salesforce.case_priority', 'Media');
        config()->set('services.salesforce.case_origin', 'Email');

        SiteSetting::current()->update([
            'site_name' => 'iLeben',
            'contact_email' => 'inscripciones@ileben.cl',
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text', 'required' => false],
                ['key' => 'arrival_channel', 'label' => 'Medio de llegada', 'type' => 'text', 'required' => false],
            ],
        ]);

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
                'utm_source' => 'direct',
                'utm_campaign' => 'BlackFriday',
            ],
            'submitted_at' => now(),
        ]);

        $payload = app(SalesforceCaseMapper::class)->map($submission);

        $this->assertSame('iLeben', $payload['SuppliedName'] ?? null);
        $this->assertSame('inscripciones@ileben.cl', $payload['SuppliedEmail'] ?? null);
        $this->assertSame('992285134', $payload['SuppliedPhone'] ?? null);
        $this->assertSame('992285134', $payload['ContactPhone'] ?? null);
        $this->assertSame('alejandro@example.com', $payload['ContactEmail'] ?? null);
        $this->assertSame('11.455.798-6', $payload['RUT__c'] ?? null);
        $this->assertSame('BlackFriday', $payload['Subject'] ?? null);
        $this->assertSame('Email', $payload['Origin'] ?? null);
        $this->assertSame('Media', $payload['Priority'] ?? null);
        $this->assertSame('0128c000002wPrTAAU', $payload['RecordTypeId'] ?? null);
        $this->assertSame('02sU100000aMcxzIAC', $payload['SourceId'] ?? null);
        $this->assertSame('Edificio Indigo', $payload['Nombre_Proyecto__c'] ?? null);
        $this->assertSame('Edificio Indigo', $payload['Proyecto_Formulario__c'] ?? null);
        $this->assertSame('Puerto Varas', $payload['En_que_lugar__c'] ?? null);
        $this->assertStringContainsString('Nombre: Alejandro', $payload['Description'] ?? '');
        $this->assertStringContainsString('Proyecto: Edificio Indigo', $payload['Description'] ?? '');
        $this->assertStringContainsString('UTM Source: direct', $payload['Description'] ?? '');
    }
}
