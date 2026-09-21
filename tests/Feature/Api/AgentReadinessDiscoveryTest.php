<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class AgentReadinessDiscoveryTest extends TestCase
{
    public function test_markdown_negotiation_returns_200_with_markdown_tokens(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'text/markdown',
        ])->get('/');

        $response->assertOk();
        $this->assertStringContainsString('text/markdown', (string) $response->headers->get('Content-Type'));
        $this->assertEquals('Accept', $response->headers->get('Vary'));
        $this->assertNotEmpty($response->headers->get('x-markdown-tokens'));
        $this->assertStringContainsString('# iLeben', $response->getContent());
    }

    public function test_auth_md_returns_markdown_with_auth_md_heading(): void
    {
        $response = $this->get('/auth.md');

        $response->assertOk();
        $this->assertStringContainsString('text/markdown', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('# Auth.md', $response->getContent());
        $this->assertStringContainsString('/agent/register', $response->getContent());
    }

    public function test_oauth_authorization_server_discovery(): void
    {
        $response = $this->get('/.well-known/oauth-authorization-server');

        $response->assertOk();
        $response->assertJsonStructure([
            'issuer',
            'authorization_endpoint',
            'token_endpoint',
            'registration_endpoint',
            'jwks_uri',
            'response_types_supported',
            'grant_types_supported',
            'scopes_supported',
            'agent_auth' => [
                'skill',
                'register_uri',
                'supported_identity_types',
                'identity_types_supported',
            ],
        ]);
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_openid_configuration_discovery(): void
    {
        $response = $this->get('/.well-known/openid-configuration');

        $response->assertOk();
        $response->assertJsonStructure([
            'issuer',
            'authorization_endpoint',
            'token_endpoint',
            'jwks_uri',
            'scopes_supported',
            'response_types_supported',
            'grant_types_supported',
        ]);
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_oauth_protected_resource_metadata(): void
    {
        $response = $this->get('/.well-known/oauth-protected-resource');

        $response->assertOk();
        $response->assertJsonStructure([
            'resource',
            'authorization_servers',
            'scopes_supported',
            'bearer_methods_supported',
            'resource_documentation',
        ]);
        $this->assertContains('header', $response->json('bearer_methods_supported'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_mcp_server_card_discovery(): void
    {
        $response = $this->get('/.well-known/mcp/server-card.json');

        $response->assertOk();
        $response->assertJsonStructure([
            'serverInfo' => [
                'name',
                'version',
                'title',
                'description',
            ],
            'transport' => [
                'type',
                'endpoint',
            ],
            'capabilities' => [
                'tools',
                'resources',
                'prompts',
            ],
        ]);
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_agent_skills_discovery_index(): void
    {
        $response = $this->get('/.well-known/agent-skills/index.json');

        $response->assertOk();
        $response->assertJsonStructure([
            '$schema',
            'skills' => [
                '*' => [
                    'name',
                    'type',
                    'description',
                    'url',
                    'digest',
                ],
            ],
        ]);

        $this->assertEquals('https://schemas.agentskills.io/discovery/0.2.0/schema.json', $response->json('$schema'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_agent_skill_file_serving(): void
    {
        $response = $this->get('/.well-known/agent-skills/catalog-search/SKILL.md');

        $response->assertOk();
        $this->assertStringContainsString('text/markdown', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Catalog Search', $response->getContent());
    }

    public function test_ard_manifest_discovery(): void
    {
        $response = $this->get('/.well-known/ai-catalog.json');

        $response->assertOk();
        $response->assertJsonStructure([
            'specVersion',
            'host' => [
                'displayName',
                'identifier',
            ],
            'entries' => [
                '*' => [
                    'identifier',
                    'displayName',
                    'type',
                    'url',
                    'representativeQueries',
                ],
            ],
        ]);
        $this->assertEquals('1.0', $response->json('specVersion'));
        $this->assertStringStartsWith('urn:air:', $response->json('entries.0.identifier'));
        $this->assertGreaterThanOrEqual(2, count($response->json('entries.0.representativeQueries')));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_robots_txt_contains_agentmap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $this->assertStringContainsString('Agentmap:', $response->getContent());
        $this->assertStringContainsString('/.well-known/ai-catalog.json', $response->getContent());
    }
}
