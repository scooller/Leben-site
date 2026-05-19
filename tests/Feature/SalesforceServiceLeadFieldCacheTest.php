<?php

namespace Tests\Feature;

use App\Services\Salesforce\SalesforceService;
use Illuminate\Support\Facades\Cache;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Tests\TestCase;

class SalesforceServiceLeadFieldCacheTest extends TestCase
{
    public function test_it_filters_non_creatable_lead_fields_using_describe_metadata(): void
    {
        Cache::forget('salesforce:lead:creatable-fields');
        Cache::forget('salesforce:lead:unavailable-fields');

        Forrest::shouldReceive('describe')
            ->once()
            ->with('Lead')
            ->andReturn([
                'fields' => [
                    ['name' => 'FirstName', 'createable' => true],
                    ['name' => 'LastName', 'createable' => true],
                    ['name' => 'Company', 'createable' => true],
                    ['name' => 'Email', 'createable' => true],
                    ['name' => 'wsp_owner__c', 'createable' => true],
                    ['name' => 'Whatsapp_Link__c', 'createable' => false],
                ],
            ]);

        Forrest::shouldReceive('sobjects')
            ->once()
            ->withArgs(function (string $sObject, array $request): bool {
                if ($sObject !== 'Lead' || ($request['method'] ?? null) !== 'post') {
                    return false;
                }

                $body = $request['body'] ?? [];

                return ! array_key_exists('Whatsapp_Link__c', $body)
                    && ! array_key_exists('Unknown_Field__c', $body)
                    && ($body['FirstName'] ?? null) === 'Camila'
                    && ($body['Email'] ?? null) === 'camila@example.com';
            })
            ->andReturn([
                'id' => '00QU1000002abcDIAQ',
                'success' => true,
                'errors' => [],
            ]);

        $response = app(SalesforceService::class)->createLead([
            'FirstName' => 'Camila',
            'LastName' => 'Perez',
            // 'Company' => 'iLeben',
            'Email' => 'camila@example.com',
            'wsp_owner__c' => '+56911111111',
            'Whatsapp_Link__c' => 'https://wa.me/56911111111',
            'Unknown_Field__c' => 'valor',
        ]);

        $this->assertSame('00QU1000002abcDIAQ', $response['id'] ?? null);
        $this->assertTrue((bool) ($response['success'] ?? false));
    }

    public function test_it_removes_known_unavailable_lead_fields_before_first_attempt(): void
    {
        Cache::put('salesforce:lead:unavailable-fields', [
            'Description',
            'Whatsapp_Link__c',
        ], now()->addDay());

        Forrest::shouldReceive('sobjects')
            ->once()
            ->withArgs(function (string $sObject, array $request): bool {
                if ($sObject !== 'Lead' || ($request['method'] ?? null) !== 'post') {
                    return false;
                }

                $body = $request['body'] ?? [];

                return ! array_key_exists('Description', $body)
                    && ! array_key_exists('Whatsapp_Link__c', $body)
                    && ($body['FirstName'] ?? null) === 'Camila'
                    && ($body['Email'] ?? null) === 'camila@example.com';
            })
            ->andReturn([
                'id' => '00QU1000002abcDIAQ',
                'success' => true,
                'errors' => [],
            ]);

        $response = app(SalesforceService::class)->createLead([
            'FirstName' => 'Camila',
            'LastName' => 'Perez',
            'Email' => 'camila@example.com',
            'Description' => 'Lead desde formulario',
            'Whatsapp_Link__c' => 'https://wa.me/56912345678',
        ]);

        $this->assertSame('00QU1000002abcDIAQ', $response['id'] ?? null);
        $this->assertTrue((bool) ($response['success'] ?? false));
    }
}
