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

    public function test_it_overwrites_campaign_when_utm_source_is_brevo_and_sale_is_active(): void
    {
        $channel = ContactChannel::factory()->create(['slug' => 'test-channel']);

        \App\Models\SiteSetting::current()->update([
            'evento_sale' => true,
            'extra_settings' => [
                'sale_utm_campaign' => 'CyberBrevo2026',
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
        ]);

        $response->assertCreated();

        $submission = ContactSubmission::query()->latest('id')->first();
        $this->assertNotNull($submission);
        $this->assertSame('CyberBrevo2026', $submission->fields['utm_campaign'] ?? null);
    }

    public function test_it_defaults_campaign_to_sale_utm_campaign_when_sale_is_active_and_campaign_is_missing_or_auto_tagging(): void
    {
        $channel = ContactChannel::factory()->create(['slug' => 'test-channel-sale-default']);

        \App\Models\SiteSetting::current()->update([
            'evento_sale' => true,
            'extra_settings' => [
                'sale_utm_campaign' => 'CyberSaleGeneral',
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
        ]);

        $response->assertCreated();

        $submission = ContactSubmission::query()->latest('id')->first();
        $this->assertNotNull($submission);
        $this->assertSame('CyberSaleGeneral', $submission->fields['utm_campaign'] ?? null);
    }
}

