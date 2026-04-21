<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\UserActivitiesPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserActivitiesPageNewUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_recent_spatie_users_in_user_activities_page(): void
    {
        $adminRole = Role::findOrCreate('admin', 'web');
        $clienteRole = Role::findOrCreate('cliente', 'web');

        $admin = User::factory()->create([
            'name' => 'Admin User',
            'user_type' => 'admin',
        ]);
        $admin->assignRole($adminRole);

        $recentSpatieUser = User::factory()->create([
            'name' => 'Nuevo Cliente Spatie',
            'email' => 'nuevo-cliente@example.com',
        ]);
        $recentSpatieUser->assignRole($clienteRole);

        $olderSpatieUser = User::factory()->create([
            'name' => 'Cliente Anterior Spatie',
            'email' => 'cliente-anterior@example.com',
        ]);
        $olderSpatieUser->assignRole($clienteRole);
        $olderSpatieUser->forceFill([
            'created_at' => now()->subDays(2),
        ])->saveQuietly();

        $plainUser = User::factory()->create([
            'name' => 'Usuario Sin Rol',
            'email' => 'sin-rol@example.com',
        ]);

        $this->actingAs($admin);

        Livewire::test(UserActivitiesPage::class)
            ->assertSee('Usuarios nuevos (Spatie)')
            ->assertSee($recentSpatieUser->name)
            ->assertSee($olderSpatieUser->name)
            ->assertSee($recentSpatieUser->email)
            ->assertSee($olderSpatieUser->email)
            ->assertDontSee($plainUser->email);
    }
}
