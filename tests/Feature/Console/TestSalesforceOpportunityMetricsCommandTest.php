<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TestSalesforceOpportunityMetricsCommandTest extends TestCase
{
    public function test_command_queries_opportunities_and_prints_summary(): void
    {
        config()->set('forrest.credentials.loginURL', 'https://example.salesforce.com');
        config()->set('forrest.credentials.consumerKey', 'key');
        config()->set('forrest.credentials.consumerSecret', 'secret');
        config()->set('forrest.credentials.username', 'user@example.com');
        config()->set('forrest.credentials.password', 'passTOKEN');
        config()->set('forrest.version', '60.0');

        Http::fake([
            'https://example.salesforce.com/services/oauth2/token' => Http::response([
                'access_token' => 'access-token',
                'instance_url' => 'https://org.my.salesforce.com',
            ], 200),
            'https://org.my.salesforce.com/services/data/v60.0/query*' => Http::response([
                'totalSize' => 2,
                'done' => true,
                'records' => [
                    [
                        'Id' => '006AAA0000000001',
                        'Broker__c' => 'a0uAAA0000000001',
                        'Broker__r' => ['Name' => 'Broker Uno'],
                        'Proyecto__c' => 'a0JAAA0000000001',
                        'Proyecto__r' => ['Name' => 'Proyecto Uno'],
                        'StageName' => 'Cotizacion',
                        'IsWon' => false,
                        'IsClosed' => false,
                        'CreatedDate' => '2026-05-11T10:00:00.000+0000',
                    ],
                    [
                        'Id' => '006AAA0000000002',
                        'Broker__c' => '',
                        'Broker__r' => ['Name' => null],
                        'Proyecto__c' => 'a0JAAA0000000002',
                        'Proyecto__r' => ['Name' => 'Proyecto Dos'],
                        'StageName' => 'Cerrada ganada',
                        'IsWon' => true,
                        'IsClosed' => true,
                        'CreatedDate' => '2026-05-11T09:00:00.000+0000',
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('salesforce:test-opportunity-metrics --limit=2')
            ->expectsOutput('Query ejecutada con éxito. API v60.0')
            ->expectsOutput('Total devuelto por Salesforce: 2')
            ->expectsOutput('Registros inspeccionados: 2')
            ->expectsOutput('Sin Broker__c: 1')
            ->expectsOutput('Sin Proyecto__c: 0')
            ->assertSuccessful();
    }

    public function test_command_fails_when_credentials_are_missing(): void
    {
        config()->set('forrest.credentials', []);

        $this->artisan('salesforce:test-opportunity-metrics')
            ->expectsOutput('Faltan credenciales Salesforce en configuración (forrest.credentials).')
            ->assertFailed();
    }
}
