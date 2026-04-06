<?php

namespace Tests\Feature;

use App\Services\Salesforce\SalesforceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Tests\TestCase;

class SalesforceServiceDocumentUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('salesforce:org_id:auto');
    }

    public function test_it_builds_public_imageserver_url_when_public_site_and_org_id_are_configured(): void
    {
        config()->set('services.salesforce.public_site_url', 'https://leben.my.salesforce-sites.com');
        config()->set('services.salesforce.org_id', '00D8c0000018fVyEAI');
        config()->set('services.salesforce.instance_url', 'https://leben.my.salesforce.com');

        $service = Mockery::mock(SalesforceService::class)->makePartial();
        $service->shouldReceive('query')
            ->once()
            ->andReturn([
                [
                    'Id' => '015U1000003AUhRIAW',
                    'Name' => 'Edificio Indi - Cotizador Portada',
                    'Type' => 'jpg',
                    'BodyLength' => 982938,
                    'Body' => '/services/data/v57.0/sobjects/Document/015U1000003AUhRIAW/Body',
                    'LastModifiedDate' => '2024-12-18T18:34:59.000+0000',
                ],
            ]);

        $documents = $service->findPublicCotizadorDocuments(0);

        $this->assertCount(1, $documents);
        $url = $documents[0]['download_url'];

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://leben.my.salesforce-sites.com/servlet/servlet.ImageServer?', $url);

        $query = [];
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

        $this->assertSame('015U1000003AUhRIAW', $query['id'] ?? null);
        $this->assertSame('00D8c0000018fVyEAI', $query['oid'] ?? null);
        $this->assertSame(
            (string) Carbon::parse('2024-12-18T18:34:59.000+0000')->getTimestampMs(),
            $query['lastMod'] ?? null,
        );
    }

    public function test_it_falls_back_to_rest_body_url_when_public_site_settings_are_missing(): void
    {
        config()->set('services.salesforce.public_site_url', null);
        config()->set('services.salesforce.org_id', null);
        config()->set('services.salesforce.instance_url', 'https://leben.my.salesforce.com');

        Forrest::shouldReceive('identity')
            ->once()
            ->andThrow(new \RuntimeException('Identity not available'));

        Forrest::shouldReceive('query')
            ->once()
            ->with('SELECT Id FROM Organization LIMIT 1')
            ->andThrow(new \RuntimeException('Organization query not available'));

        $service = Mockery::mock(SalesforceService::class)->makePartial();
        $service->shouldReceive('query')
            ->once()
            ->andReturn([
                [
                    'Id' => '015U1000003AUhRIAW',
                    'Name' => 'Edificio Indi - Cotizador Portada',
                    'Type' => 'jpg',
                    'BodyLength' => 982938,
                    'Body' => '/services/data/v57.0/sobjects/Document/015U1000003AUhRIAW/Body',
                    'LastModifiedDate' => '2024-12-18T18:34:59.000+0000',
                ],
            ]);

        $documents = $service->findPublicCotizadorDocuments(0);

        $this->assertCount(1, $documents);
        $this->assertSame(
            'https://leben.my.salesforce.com/services/data/v57.0/sobjects/Document/015U1000003AUhRIAW/Body',
            $documents[0]['download_url'],
        );
    }

    public function test_it_derives_public_site_and_org_id_when_not_configured(): void
    {
        config()->set('services.salesforce.public_site_url', null);
        config()->set('services.salesforce.org_id', null);
        config()->set('services.salesforce.instance_url', 'https://leben.my.salesforce.com');

        Forrest::shouldReceive('identity')
            ->once()
            ->andReturn([
                'id' => 'https://login.salesforce.com/id/00D8c0000018fVyEAI/0058c00000ABCDe',
            ]);

        $service = Mockery::mock(SalesforceService::class)->makePartial();
        $service->shouldReceive('query')
            ->once()
            ->andReturn([
                [
                    'Id' => '015U1000003AUhRIAW',
                    'Name' => 'Edificio Indi - Cotizador Portada',
                    'Type' => 'jpg',
                    'BodyLength' => 982938,
                    'Body' => '/services/data/v57.0/sobjects/Document/015U1000003AUhRIAW/Body',
                    'LastModifiedDate' => '2024-12-18T18:34:59.000+0000',
                ],
            ]);

        $documents = $service->findPublicCotizadorDocuments(0);

        $this->assertCount(1, $documents);
        $url = $documents[0]['download_url'];

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://leben.my.salesforce-sites.com/servlet/servlet.ImageServer?', $url);

        $query = [];
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

        $this->assertSame('015U1000003AUhRIAW', $query['id'] ?? null);
        $this->assertSame('00D8c0000018fVyEAI', $query['oid'] ?? null);
    }
}
