<?php

namespace Tests\Feature;

use App\Models\FrontendPreviewLink;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class FrontendPreviewLinkSiteConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_config_keeps_catalog_hidden_without_preview_token(): void
    {
        SiteSetting::current()->update([
            'mostrar_plantas' => false,
        ]);

        $response = $this->getJson('/api/v1/site-config');

        $response
            ->assertOk()
            ->assertJsonPath('mostrar_plantas', false);
    }

    public function test_site_config_enables_catalog_with_valid_preview_token(): void
    {
        SiteSetting::current()->update([
            'mostrar_plantas' => false,
        ]);

        $plainToken = Str::random(64);

        FrontendPreviewLink::query()->create([
            'name' => 'preview-general',
            'token' => $plainToken,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $response = $this->getJson('/api/v1/site-config?preview_token='.$plainToken);

        $response
            ->assertOk()
            ->assertJsonPath('mostrar_plantas', true);
    }

    public function test_site_config_does_not_enable_catalog_with_expired_preview_token(): void
    {
        SiteSetting::current()->update([
            'mostrar_plantas' => false,
        ]);

        $plainToken = Str::random(64);

        FrontendPreviewLink::query()->create([
            'name' => 'preview-expirado',
            'token' => $plainToken,
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $response = $this->getJson('/api/v1/site-config?preview_token='.$plainToken);

        $response
            ->assertOk()
            ->assertJsonPath('mostrar_plantas', false);
    }

    public function test_site_config_respects_ip_filter_for_preview_token(): void
    {
        SiteSetting::current()->update([
            'mostrar_plantas' => false,
        ]);

        $plainToken = Str::random(64);

        FrontendPreviewLink::query()->create([
            'name' => 'preview-ip',
            'token' => $plainToken,
            'allowed_ip' => '203.0.113.10',
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->getJson('/api/v1/site-config?preview_token='.$plainToken)
            ->assertOk()
            ->assertJsonPath('mostrar_plantas', false);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->getJson('/api/v1/site-config?preview_token='.$plainToken)
            ->assertOk()
            ->assertJsonPath('mostrar_plantas', true);
    }

    public function test_site_config_accepts_preview_token_from_header(): void
    {
        SiteSetting::current()->update([
            'mostrar_plantas' => false,
        ]);

        $plainToken = Str::random(64);

        FrontendPreviewLink::query()->create([
            'name' => 'preview-header',
            'token' => $plainToken,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $this->withHeaders([
            'X-Preview-Token' => $plainToken,
        ])->getJson('/api/v1/site-config')
            ->assertOk()
            ->assertJsonPath('mostrar_plantas', true);
    }
}
