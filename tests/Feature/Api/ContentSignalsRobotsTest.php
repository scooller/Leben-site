<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ContentSignalsRobotsTest extends TestCase
{
    public function test_it_returns_robots_txt_with_content_signals(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Content-Signal:', $content);
        $this->assertStringContainsString('ai-train=no', $content);
        $this->assertStringContainsString('search=yes', $content);
        $this->assertStringContainsString('ai-input=no', $content);
        $this->assertStringContainsString('Sitemap:', $content);
    }
}
