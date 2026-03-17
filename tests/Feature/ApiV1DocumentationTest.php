<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiV1DocumentationTest extends TestCase
{
    public function test_api_v1_root_returns_documentation(): void
    {
        $response = $this->getJson('/api/v1');

        $response
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('info.title', 'iLeben API')
            ->assertJsonPath('info.version', 'v1')
            ->assertJsonPath('paths./site-config.get.summary', 'Configuración pública del sitio')
            ->assertJsonPath('paths./me.get.security.0.bearerAuth.0', null)
            ->assertJsonPath('paths./proyectos/{id}.get.parameters.0.$ref', '#/components/parameters/Id')
            ->assertJsonPath('paths./login.post.operationId', 'login')
            ->assertJsonPath('components.securitySchemes.bearerAuth.scheme', 'bearer')
            ->assertJsonPath('components.parameters.SessionToken.in', 'path')
            ->assertJsonStructure([
                'info' => [
                    'title',
                    'version',
                    'description',
                ],
                'tags',
                'servers',
                'paths',
                'components' => [
                    'securitySchemes' => [
                        'bearerAuth' => [
                            'type',
                            'scheme',
                            'bearerFormat',
                        ],
                    ],
                    'parameters',
                ],
            ]);
    }

    public function test_api_v1_root_with_trailing_slash_returns_documentation(): void
    {
        $response = $this->getJson('/api/v1/');

        $response
            ->assertOk()
            ->assertJsonPath('info.version', 'v1');
    }
}
