<?php

namespace Tests\Feature;

use App\Models\ContactChannel;
use App\Models\ContactSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactSubmissionApiTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(string $channelSlug): array
    {
        return [
            'channel' => $channelSlug,
            'fields' => [
                'name' => 'Juan Pérez',
                'email' => 'juan@example.cl',
                'message' => 'Quiero más información',
                'comuna' => 'Santiago',
                'proyecto' => 'Argomedo',
            ],
        ];
    }

    public function test_channel_is_required(): void
    {
        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'name' => 'Juan',
                'email' => 'juan@example.cl',
                'message' => 'Test',
                'comuna' => 'Santiago',
                'proyecto' => 'Argomedo',
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['channel']);
    }

    public function test_channel_must_be_active_and_exist(): void
    {
        $response = $this->postJson('/api/v1/contact-submissions', [
            'channel' => 'nonexistent-channel',
            'fields' => [
                'name' => 'Juan',
                'email' => 'juan@example.cl',
                'message' => 'Test',
                'comuna' => 'Santiago',
                'proyecto' => 'Argomedo',
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['channel']);
    }

    public function test_inactive_channel_is_rejected(): void
    {
        $channel = ContactChannel::factory()->inactive()->create(['slug' => 'inactive-canal']);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'channel' => $channel->slug,
            'fields' => [
                'name' => 'Juan',
                'email' => 'juan@example.cl',
                'message' => 'Test',
                'comuna' => 'Santiago',
                'proyecto' => 'Argomedo',
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['channel']);
    }

    public function test_submission_is_created_with_valid_channel_in_body(): void
    {
        $channel = ContactChannel::query()->where('slug', 'sale')->firstOrFail();

        $response = $this->postJson('/api/v1/contact-submissions', $this->validPayload('sale'));

        $response->assertCreated()
            ->assertJsonPath('message', 'Tu mensaje fue enviado correctamente.')
            ->assertJsonStructure(['message', 'id']);

        $this->assertDatabaseHas(ContactSubmission::class, [
            'contact_channel_id' => $channel->id,
            'email' => 'juan@example.cl',
        ]);
    }

    public function test_submission_is_created_with_channel_via_header(): void
    {
        $channel = ContactChannel::factory()->create(['slug' => 'api-header-canal']);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'name' => 'Juan Pérez',
                'email' => 'juan@example.cl',
                'message' => 'Test vía header',
                'comuna' => 'Santiago',
                'proyecto' => 'Argomedo',
            ],
        ], ['X-Contact-Channel' => 'api-header-canal']);

        $response->assertCreated();

        $this->assertDatabaseHas(ContactSubmission::class, [
            'contact_channel_id' => $channel->id,
        ]);
    }

    public function test_channel_in_body_takes_precedence_over_header(): void
    {
        $bodyChannel = ContactChannel::factory()->create(['slug' => 'body-canal-test']);
        ContactChannel::factory()->create(['slug' => 'header-canal-test']);

        $response = $this->postJson('/api/v1/contact-submissions', $this->validPayload('body-canal-test'), [
            'X-Contact-Channel' => 'header-canal-test',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas(ContactSubmission::class, [
            'contact_channel_id' => $bodyChannel->id,
        ]);
    }

    public function test_domain_patterns_no_longer_resolve_channel(): void
    {
        ContactChannel::factory()->create([
            'slug' => 'domain-canal',
            'domain_patterns' => ['example.cl'],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'name' => 'Juan',
                'email' => 'juan@example.cl',
                'message' => 'Test',
                'comuna' => 'Santiago',
                'proyecto' => 'Argomedo',
                'utm_site' => 'example.cl',
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['channel']);
    }

    public function test_it_overwrites_campaign_when_utm_source_is_brevo_and_sale_is_active_from_frontend(): void
    {
        $channel = ContactChannel::factory()->create(['slug' => 'test-channel']);

        \App\Models\SiteSetting::current()->update([
            'evento_sale' => true,
            'extra_settings' => [
                'sale_utm_campaign' => 'CyberBrevo2026',
                'sale_utm_campaign_channels' => [(string) $channel->id],
            ],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'channel' => 'test-channel',
            'fields' => [
                'name' => 'Juan',
                'email' => 'juan@example.cl',
                'message' => 'Test',
                'comuna' => 'Santiago',
                'proyecto' => 'Argomedo',
                'utm_source' => 'Brevo',
                'utm_campaign' => 'original-newsletter',
            ],
        ], [
            'Origin' => 'http://localhost:5173',
        ]);

        $response->assertCreated();

        $submission = ContactSubmission::query()->latest('id')->first();
        $this->assertNotNull($submission);
        $this->assertSame('CyberBrevo2026', $submission->fields['utm_campaign'] ?? null);
    }

    public function test_it_does_not_overwrite_campaign_when_request_comes_from_api(): void
    {
        $channel = ContactChannel::factory()->create(['slug' => 'test-channel-api']);

        \App\Models\SiteSetting::current()->update([
            'evento_sale' => true,
            'extra_settings' => [
                'sale_utm_campaign' => 'CyberBrevo2026',
                'sale_utm_campaign_channels' => [(string) $channel->id],
            ],
        ]);

        // Request proveniente de API externa sin Origin del front
        $response = $this->postJson('/api/v1/contact-submissions', [
            'channel' => 'test-channel-api',
            'fields' => [
                'name' => 'Juan API',
                'email' => 'juanapi@example.cl',
                'message' => 'Test',
                'comuna' => 'Santiago',
                'proyecto' => 'Argomedo',
                'utm_source' => 'Brevo',
                'utm_campaign' => 'original-newsletter',
            ],
        ]);

        $response->assertCreated();

        $submission = ContactSubmission::query()->latest('id')->first();
        $this->assertNotNull($submission);
        $this->assertSame('original-newsletter', $submission->fields['utm_campaign'] ?? null);
    }

    public function test_it_does_not_overwrite_campaign_when_channel_is_not_selected_even_from_frontend(): void
    {
        $channel = ContactChannel::factory()->create(['slug' => 'test-channel-unselected']);

        \App\Models\SiteSetting::current()->update([
            'evento_sale' => true,
            'extra_settings' => [
                'sale_utm_campaign' => 'CyberSale2026',
                'sale_utm_campaign_channels' => ['999999'], // otro canal
            ],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'channel' => 'test-channel-unselected',
            'fields' => [
                'name' => 'Carlos',
                'email' => 'carlos@example.cl',
                'message' => 'Test',
                'comuna' => 'Santiago',
                'proyecto' => 'Argomedo',
                'utm_source' => 'google',
                'utm_campaign' => 'google-ads-inversion',
            ],
        ], [
            'Origin' => 'http://localhost:5173',
        ]);

        $response->assertCreated();

        $submission = ContactSubmission::query()->latest('id')->first();
        $this->assertNotNull($submission);
        $this->assertSame('google-ads-inversion', $submission->fields['utm_campaign'] ?? null);
    }

    public function test_it_defaults_campaign_to_sale_utm_campaign_when_sale_is_active_and_from_frontend(): void
    {
        $channel = ContactChannel::factory()->create(['slug' => 'test-channel-sale-default']);

        \App\Models\SiteSetting::current()->update([
            'evento_sale' => true,
            'extra_settings' => [
                'sale_utm_campaign' => 'CyberSaleGeneral',
                'sale_utm_campaign_channels' => [(string) $channel->id],
            ],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'channel' => 'test-channel-sale-default',
            'fields' => [
                'name' => 'Maria',
                'email' => 'maria@example.cl',
                'message' => 'Test',
                'comuna' => 'Santiago',
                'proyecto' => 'Argomedo',
                'utm_source' => 'google',
                'utm_campaign' => 'auto-tagging',
            ],
        ], [
            'Origin' => 'http://localhost:5173',
        ]);

        $response->assertCreated();

        $submission = ContactSubmission::query()->latest('id')->first();
        $this->assertNotNull($submission);
        $this->assertSame('CyberSaleGeneral', $submission->fields['utm_campaign'] ?? null);
    }
}
