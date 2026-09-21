<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class MarkdownContentNegotiationTest extends TestCase
{
    public function test_it_returns_markdown_when_requested_with_accept_header(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'text/markdown',
        ])->get('/');

        $response->assertOk();
        $this->assertStringContainsString('text/markdown', (string) $response->headers->get('Content-Type'));
        $this->assertEquals('Accept', $response->headers->get('Vary'));

        $tokensHeader = $response->headers->get('x-markdown-tokens');
        $this->assertNotNull($tokensHeader);
        $this->assertGreaterThan(0, (int) $tokensHeader);

        $body = $response->getContent();
        $this->assertStringContainsString('# iLeben', $body);
        $this->assertStringContainsString('api-catalog', $body);
        $this->assertStringContainsString('/api/v1', $body);
    }

    public function test_it_keeps_html_default_when_no_markdown_accept_header(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ])->get('/');

        // Default behavior for browser traffic on root is redirect to /admin
        $this->assertTrue($response->isRedirection());
    }

    public function test_it_serves_llms_txt_endpoint(): void
    {
        $response = $this->get('/llms.txt');

        $response->assertOk();
        $this->assertStringContainsString('text/markdown', (string) $response->headers->get('Content-Type'));
        $this->assertNotNull($response->headers->get('x-markdown-tokens'));
    }

    public function test_it_serves_well_known_llms_txt_endpoint(): void
    {
        $response = $this->get('/.well-known/llms.txt');

        $response->assertOk();
        $this->assertStringContainsString('text/markdown', (string) $response->headers->get('Content-Type'));
        $this->assertNotNull($response->headers->get('x-markdown-tokens'));
    }

    public function test_it_negotiates_markdown_on_subpages(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'text/markdown',
        ])->get('/plantas');

        $response->assertOk();
        $this->assertStringContainsString('text/markdown', (string) $response->headers->get('Content-Type'));
        $this->assertNotNull($response->headers->get('x-markdown-tokens'));
    }
}
