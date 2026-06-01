<?php

namespace Tests\Feature\Api;

use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiToken;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;
    use WithApiToken;

    public function test_proyectos_endpoint_returns_200_when_unauthenticated(): void
    {
        // Los endpoints públicos del catálogo requieren un bearer token válido
        // (EnsureTokenOriginIsAuthorized) pero NO requieren auth:sanctum (usuario login).
        $this->setUpApiToken();

        SiteSetting::current()->update([
            'mostrar_plantas' => false,
        ]);

        $project = Proyecto::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->get('/api/v1/proyectos');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $project->id,
            ]);
    }

    public function test_me_endpoint_returns_401_when_unauthenticated(): void
    {
        // Sin token → auth:sanctum devuelve 401 Unauthenticated.
        $response = $this->get('/api/v1/me');

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_plantas_endpoint_returns_200_when_unauthenticated(): void
    {
        $this->setUpApiToken();

        SiteSetting::current()->update([
            'mostrar_plantas' => false,
        ]);

        $project = Proyecto::factory()->create([
            'is_active' => true,
        ]);

        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        $response = $this->get('/api/v1/plantas');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $plant->id,
            ]);
    }
}
