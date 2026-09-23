<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class AgentDiscoveryHeadersTest extends TestCase
{
    public function test_it_returns_link_headers_on_root_web_response(): void
    {
        $response = $this->get('/');

        $linkHeader = $response->headers->get('Link');
        $this->assertNotNull($linkHeader);
        $this->assertStringContainsString('</.well-known/api-catalog>; rel="api-catalog"', $linkHeader);
        $this->assertStringContainsString('</api/v1>; rel="service-desc"', $linkHeader);
        $this->assertStringContainsString('</api/v1>; rel="service-doc"', $linkHeader);
    }

    public function test_it_serves_rfc_9727_api_catalog(): void
    {
        $response = $this->get('/.well-known/api-catalog');

        $response->assertOk();
        $this->assertStringContainsString('application/linkset+json', (string) $response->headers->get('Content-Type'));

        $linkHeader = $response->headers->get('Link');
        $this->assertNotNull($linkHeader);
        $this->assertStringContainsString('rel="api-catalog"', $linkHeader);

        $json = $response->json();
        $this->assertArrayHasKey('linkset', $json);
        $this->assertNotEmpty($json['linkset']);

        $entry = $json['linkset'][0];
        $this->assertEquals('https://www.rfc-editor.org/rfc/rfc9727', $entry['profile']);
        $this->assertNotEmpty($entry['item']);

        $rels = array_column($entry['item'], 'rel');
        $this->assertContains('service-desc', $rels);
        $this->assertContains('service-doc', $rels);
    }

    public function test_it_responds_to_head_request_on_api_catalog(): void
    {
        $response = $this->head('/.well-known/api-catalog');

        $response->assertOk();
        $linkHeader = $response->headers->get('Link');
        $this->assertNotNull($linkHeader);
        $this->assertStringContainsString('rel="api-catalog"', $linkHeader);
    }
}
