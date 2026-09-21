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

    public function test_payment_gateways_endpoint_returns_200_when_unauthenticated_with_api_token(): void
    {
        $this->setUpApiToken();

        $response = $this->get('/api/v1/payment-gateways');

        $response
            ->assertOk()
            ->assertJsonStructure(['gateways', 'count']);
    }

    public function test_payment_gateways_endpoint_returns_200_with_preview_token_without_bearer_token(): void
    {
        $plainToken = \Illuminate\Support\Str::random(64);

        \App\Models\FrontendPreviewLink::query()->create([
            'name' => 'preview-test',
            'token' => $plainToken,
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->get('/api/v1/payment-gateways?preview_token='.$plainToken);

        $response
            ->assertOk()
            ->assertJsonStructure(['gateways', 'count']);
    }

    public function test_reservations_endpoint_allows_anonymous_reservation_with_api_token(): void
    {
        $this->setUpApiToken();

        $project = Proyecto::factory()->create(['is_active' => true]);
        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/reservations', [
            'plant_id' => $plant->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'reservation' => ['id', 'session_token', 'plant_id', 'status', 'expires_at', 'remaining_seconds'],
            ]);
    }

    public function test_reservations_endpoint_allows_anonymous_reservation_with_preview_token(): void
    {
        $plainToken = \Illuminate\Support\Str::random(64);

        \App\Models\FrontendPreviewLink::query()->create([
            'name' => 'preview-test-res',
            'token' => $plainToken,
            'expires_at' => now()->addHour(),
        ]);

        $project = Proyecto::factory()->create(['is_active' => true]);
        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/reservations?preview_token='.$plainToken, [
            'plant_id' => $plant->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'reservation' => ['id', 'session_token', 'plant_id', 'status'],
            ]);
    }

    public function test_anonymous_user_can_release_reservation_by_token(): void
    {
        $this->setUpApiToken();

        $project = Proyecto::factory()->create(['is_active' => true]);
        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        $createResponse = $this->postJson('/api/v1/reservations', [
            'plant_id' => $plant->id,
        ]);

        $sessionToken = $createResponse->json('reservation.session_token');

        $releaseResponse = $this->deleteJson('/api/v1/reservations/'.$sessionToken);

        $releaseResponse->assertOk()
            ->assertJson([
                'message' => 'Reserva liberada exitosamente.',
            ]);
    }
}
