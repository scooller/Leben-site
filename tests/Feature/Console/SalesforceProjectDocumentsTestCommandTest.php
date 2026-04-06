<?php

namespace Tests\Feature\Console;

use App\Services\Salesforce\SalesforceService;
use Mockery\MockInterface;
use Tests\TestCase;

class SalesforceProjectDocumentsTestCommandTest extends TestCase
{
    public function test_command_lists_public_project_documents(): void
    {
        $this->mock(SalesforceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getDefaultProjectDocumentNames')
                ->once()
                ->andReturn([
                    'Edificio Indi - Cotizador Portada',
                    'Edificio Indi - Cotizador Logo',
                ]);

            $mock->shouldReceive('findPublicProjectDocuments')
                ->once()
                ->with([
                    'Edificio Indi - Cotizador Portada',
                    'Edificio Indi - Cotizador Logo',
                ], null)
                ->andReturn([
                    [
                        'project_name' => 'Edificio Indi',
                        'asset_kind' => 'portada',
                        'name' => 'Edificio Indi - Cotizador Portada',
                        'id' => '015XX0000000001AAA',
                        'type' => 'png',
                        'body_length' => 1024,
                        'download_url' => 'https://example.my.salesforce.com/services/data/v57.0/sobjects/Document/015XX0000000001AAA/Body',
                    ],
                    [
                        'project_name' => 'Edificio Indi',
                        'asset_kind' => 'logo',
                        'name' => 'Edificio Indi - Cotizador Logo',
                        'id' => '015XX0000000002AAA',
                        'type' => 'svg',
                        'body_length' => 2048,
                        'download_url' => 'https://example.my.salesforce.com/services/data/v57.0/sobjects/Document/015XX0000000002AAA/Body',
                    ],
                ]);
        });

        $this->artisan('salesforce:test-project-documents')
            ->expectsOutput('Consultando documentos públicos en Salesforce...')
            ->expectsTable(
                ['Proyecto', 'Asset', 'Documento', 'Id', 'Tipo', 'BodyLength', 'Download URL'],
                [
                    ['Edificio Indi', 'portada', 'Edificio Indi - Cotizador Portada', '015XX0000000001AAA', 'png', '1024', 'https://example.my.salesforce.com/services/data/v57.0/sobjects/Document/015XX0000000001AAA/Body'],
                    ['Edificio Indi', 'logo', 'Edificio Indi - Cotizador Logo', '015XX0000000002AAA', 'svg', '2048', 'https://example.my.salesforce.com/services/data/v57.0/sobjects/Document/015XX0000000002AAA/Body'],
                ]
            )
            ->expectsOutput('Documentos recuperados: 2')
            ->assertSuccessful();
    }

    public function test_command_handles_empty_results(): void
    {
        $this->mock(SalesforceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getDefaultProjectDocumentNames')
                ->once()
                ->andReturn([
                    'Edificio Capitanes - Cotizador Portada',
                ]);

            $mock->shouldReceive('findPublicProjectDocuments')
                ->once()
                ->with([
                    'Edificio Capitanes - Cotizador Portada',
                ], null)
                ->andReturn([]);
        });

        $this->artisan('salesforce:test-project-documents')
            ->expectsOutput('Consultando documentos públicos en Salesforce...')
            ->expectsOutput('No se encontraron documentos para los nombres solicitados.')
            ->assertSuccessful();
    }
}
