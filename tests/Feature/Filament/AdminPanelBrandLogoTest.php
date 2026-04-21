<?php

namespace Tests\Feature\Filament;

use App\Models\SiteSetting;
use App\Providers\Filament\AdminPanelProvider;
use Awcodes\Curator\Models\Media;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelBrandLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_dark_mode_brand_logo_uses_logo_dark_when_available(): void
    {
        $lightLogo = $this->createMedia('branding-light-logo', 'branding-light-logo.png');
        $darkLogo = $this->createMedia('branding-dark-logo', 'branding-dark-logo.png');

        SiteSetting::current()->update([
            'logo_id' => $lightLogo->id,
            'logo_dark_id' => $darkLogo->id,
        ]);

        $panel = $this->makeAdminPanel();

        $this->assertSame($lightLogo->url, $panel->getBrandLogo());
        $this->assertSame($darkLogo->url, $panel->getDarkModeBrandLogo());
    }

    public function test_dark_mode_brand_logo_falls_back_to_light_logo_when_dark_logo_is_missing(): void
    {
        $lightLogo = $this->createMedia('branding-light-logo-fallback', 'branding-light-logo-fallback.png');

        SiteSetting::current()->update([
            'logo_id' => $lightLogo->id,
            'logo_dark_id' => null,
        ]);

        $panel = $this->makeAdminPanel();

        $this->assertSame($lightLogo->url, $panel->getBrandLogo());
        $this->assertSame($lightLogo->url, $panel->getDarkModeBrandLogo());
    }

    private function makeAdminPanel(): Panel
    {
        $provider = new AdminPanelProvider(app());

        return $provider->panel(Panel::make());
    }

    private function createMedia(string $name, string $path): Media
    {
        return Media::query()->create([
            'disk' => 'curator',
            'directory' => null,
            'visibility' => 'public',
            'name' => $name,
            'path' => $path,
            'width' => 320,
            'height' => 120,
            'size' => 10240,
            'type' => 'image/png',
            'ext' => 'png',
            'title' => $name,
        ]);
    }
}
