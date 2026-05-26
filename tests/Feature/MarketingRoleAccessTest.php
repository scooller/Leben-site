<?php

namespace Tests\Feature;

use App\Filament\Resources\Asesores\AsesorResource;
use App\Filament\Resources\ContactSubmissions\ContactSubmissions\ContactSubmissionResource;
use App\Filament\Resources\Plants\PlantResource;
use App\Filament\Resources\ShortLinks\ShortLinkResource;
use App\Models\ShortLink;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketingRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('marketing', 'web');
        Role::findOrCreate('cliente', 'web');
    }

    /**
     * @return array{0: User, 1: User, 2: User}
     */
    private function createUsers(): array
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $admin->assignRole('admin');

        $marketing = User::factory()->create(['user_type' => 'marketing']);
        $marketing->assignRole('marketing');

        $cliente = User::factory()->create(['user_type' => 'customer']);
        $cliente->assignRole('cliente');

        return [$admin, $marketing, $cliente];
    }

    public function test_panel_access_is_granted_only_to_admin_and_marketing(): void
    {
        [$admin, $marketing, $cliente] = $this->createUsers();

        $panel = new Panel;

        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertTrue($marketing->canAccessPanel($panel));
        $this->assertFalse($cliente->canAccessPanel($panel));
    }

    public function test_marketing_has_read_only_access_in_plants_and_can_manage_contacts_and_short_links(): void
    {
        [, $marketing] = $this->createUsers();

        $this->actingAs($marketing);

        $shortLink = ShortLink::factory()->create();

        $this->assertTrue(PlantResource::canViewAny());
        $this->assertFalse(PlantResource::canCreate());
        $this->assertTrue(ContactSubmissionResource::canCreate());
        $this->assertTrue(ShortLinkResource::canCreate());
        $this->assertFalse(ShortLinkResource::canDelete($shortLink));
    }

    public function test_cliente_cannot_access_marketing_resources(): void
    {
        [,, $cliente] = $this->createUsers();

        $this->actingAs($cliente);

        $this->assertFalse(AsesorResource::canViewAny());
        $this->assertFalse(ContactSubmissionResource::canCreate());
        $this->assertFalse(ShortLinkResource::canCreate());
    }
}
