<?php

namespace Tests\Feature;

use App\Services\Salesforce\SalesforceService;
use GuzzleHttp\Psr7\Response;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Tests\TestCase;

class SalesforceServiceLeadOwnerFallbackTest extends TestCase
{
    public function test_it_retries_lead_creation_with_configured_owner_on_flow_owner_blank_error(): void
    {
        config()->set('services.salesforce.lead_owner_id', 'owner-id-invalido');
        config()->set('services.salesforce.case_owner_id', '005U100000CAG4bIAH');

        $errorBody = json_encode([
            [
                'message' => 'No podemos guardar este registro porque se produjo un error en el proceso "Asignacion de Lead". INVALID_CROSS_REFERENCE_KEY: Id. del propietario: propietario no puede estar en blanco.',
                'errorCode' => 'CANNOT_EXECUTE_FLOW_TRIGGER',
                'fields' => [],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $flowException = new class('Flow owner blank error', new Response(400, [], $errorBody)) extends \RuntimeException
        {
            public function __construct(string $message, private Response $response)
            {
                parent::__construct($message);
            }

            public function getResponse(): Response
            {
                return $this->response;
            }
        };

        Forrest::shouldReceive('sobjects')
            ->once()
            ->with('Lead', [
                'method' => 'post',
                'body' => [
                    'FirstName' => 'Erika',
                    'LastName' => 'Lopez',
                    'Email' => 'erika.lopezv@gmail.com',
                ],
            ])
            ->andThrow($flowException);

        Forrest::shouldReceive('sobjects')
            ->once()
            ->withArgs(function (string $sObject, array $request): bool {
                return $sObject === 'Lead'
                    && ($request['method'] ?? null) === 'post'
                    && ($request['body']['OwnerId'] ?? null) === '005U100000CAG4bIAH';
            })
            ->andReturn([
                'id' => '00QU1000002abcDIAQ',
                'success' => true,
                'errors' => [],
            ]);

        $response = app(SalesforceService::class)->createLead([
            'FirstName' => 'Erika',
            'LastName' => 'Lopez',
            'Email' => 'erika.lopezv@gmail.com',
        ]);

        $this->assertSame('00QU1000002abcDIAQ', $response['id'] ?? null);
        $this->assertTrue((bool) ($response['success'] ?? false));
    }
}
