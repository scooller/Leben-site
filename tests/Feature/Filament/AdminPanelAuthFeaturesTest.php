<?php

namespace Tests\Feature\Filament;

use App\Providers\Filament\AdminPanelProvider;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAuthFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_enables_password_reset(): void
    {
        $panel = $this->makeAdminPanel();

        $this->assertTrue($panel->hasPasswordReset());
    }

    public function test_admin_panel_enables_profile_page(): void
    {
        $panel = $this->makeAdminPanel();

        $this->assertTrue($panel->hasProfile());
    }

    private function makeAdminPanel(): Panel
    {
        $provider = new AdminPanelProvider(app());

        return $provider->panel(Panel::make());
    }
}
