<?php

namespace Tests\Feature;

use App\Exceptions\SalesforceTokenExpiredException;
use App\Services\Salesforce\SalesforceService;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Tests\TestCase;

class SalesforceServiceTokenExpiredTest extends TestCase
{
    public function test_it_throws_specific_exception_without_reauth_when_salesforce_returns_invalid_grant_on_lead_creation(): void
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
                    ['name' => 'Email', 'createable' => true],
                ],
            ]);

        $oauthErrorBody = json_encode([
            'error' => 'invalid_grant',
            'error_description' => 'expired access/refresh token',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $oauthException = new class('invalid_grant', new Response(400, [], $oauthErrorBody)) extends \RuntimeException
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
                    'FirstName' => 'Camila',
                    'LastName' => 'Perez',
                    'Email' => 'camila@example.com',
                ],
            ])
            ->andThrow($oauthException);

        Forrest::shouldReceive('authenticate')->never();

        $this->expectException(SalesforceTokenExpiredException::class);

        app(SalesforceService::class)->createLead([
            'FirstName' => 'Camila',
            'LastName' => 'Perez',
            'Email' => 'camila@example.com',
        ]);
    }
}
