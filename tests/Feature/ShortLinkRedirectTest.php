<?php

namespace Tests\Feature;

use App\Enums\ShortLinkStatus;
use App\Jobs\RecordShortLinkVisitJob;
use App\Models\ShortLink;
use App\Models\ShortLinkVisit;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ShortLinkRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_directly_when_no_tag_manager_is_defined(): void
    {
        Queue::fake();

        $shortLink = ShortLink::factory()->create([
            'slug' => 'promo01',
            'destination_url' => 'https://example.com/landing',
            'tag_manager_id' => null,
            'status' => ShortLinkStatus::ACTIVE,
        ]);

        SiteSetting::current()->update([
            'tag_manager_id' => null,
        ]);

        $response = $this->get('/s/' . $shortLink->slug . '?utm_source=google');

        $response->assertRedirect('https://example.com/landing?utm_source=google');

        Queue::assertPushed(RecordShortLinkVisitJob::class, function (RecordShortLinkVisitJob $job) use ($shortLink): bool {
            return $job->shortLinkId === $shortLink->id
                && ($job->payload['utm_source'] ?? null) === 'google';
        });
    }

    public function test_renders_bridge_page_when_tag_manager_exists(): void
    {
        Queue::fake();

        $shortLink = ShortLink::factory()->create([
            'slug' => 'promo02',
            'destination_url' => 'https://example.com/offer',
            'tag_manager_id' => 'GTM-TEST123',
            'status' => ShortLinkStatus::ACTIVE,
        ]);

        $response = $this->get('/s/' . $shortLink->slug);

        $response
            ->assertOk()
            ->assertSee('googletagmanager.com/gtm.js', false)
            ->assertSee('short_link_click', false)
            ->assertSee('https://example.com/offer', false)
            ->assertSee('}, 150);', false);
    }

    public function test_disabled_short_link_returns_not_found(): void
    {
        $shortLink = ShortLink::factory()->disabled()->create([
            'slug' => 'promo03',
        ]);

        $response = $this->get('/s/' . $shortLink->slug);

        $response->assertNotFound();
    }

    public function test_expired_short_link_returns_not_found(): void
    {
        $shortLink = ShortLink::factory()->expired()->create([
            'slug' => 'promo04',
        ]);

        $response = $this->get('/s/' . $shortLink->slug);

        $response->assertNotFound();
    }

    public function test_visit_job_persists_visit_and_updates_counters(): void
    {
        $shortLink = ShortLink::factory()->create([
            'visits_count' => 0,
            'last_visited_at' => null,
        ]);

        $job = new RecordShortLinkVisitJob($shortLink->id, [
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'referer' => 'https://google.com',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'launch',
            'utm_term' => null,
            'utm_content' => 'cta',
            'session_fingerprint' => 'abc123',
            'query_params' => ['utm_source' => 'newsletter'],
        ]);

        $job->handle();

        $this->assertDatabaseHas('short_link_visits', [
            'short_link_id' => $shortLink->id,
            'ip_address' => '127.0.0.1',
            'utm_source' => 'newsletter',
        ]);

        $shortLink->refresh();

        $this->assertSame(1, $shortLink->visits_count);
        $this->assertNotNull($shortLink->last_visited_at);

        $visit = ShortLinkVisit::query()->where('short_link_id', $shortLink->id)->first();

        $this->assertNotNull($visit);
        $this->assertSame('email', $visit?->utm_medium);
    }
}
