<?php

namespace Tests\Feature;

use App\Filament\Resources\ApiTokens\Pages\ListApiTokens;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_creating_api_token_stores_encrypted_token_and_can_be_revealed_with_password(): void
    {
        $admin = User::factory()->create([
            'user_type' => 'admin',
            'password' => Hash::make('secret-password-123'),
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin);

        Livewire::test(ListApiTokens::class)
            ->callAction('createToken', [
                'tokenable_id' => $admin->id,
                'name' => 'test-token',
                'authorized_url' => 'https://app.test.com',
                'expires_at' => null,
            ])
            ->assertNotified('Token API creado');

        $tokenRecord = PersonalAccessToken::query()->where('name', 'test-token')->first();
        $this->assertNotNull($tokenRecord);
        $this->assertNotNull($tokenRecord->encrypted_token);
        $this->assertStringContainsString('|', $tokenRecord->encrypted_token);

        // Attempt reveal with wrong password
        Livewire::test(ListApiTokens::class)
            ->callTableAction('viewKey', $tokenRecord, [
                'password' => 'wrong-password',
            ])
            ->assertHasTableActionErrors(['password']);

        // Attempt reveal with correct password
        Livewire::test(ListApiTokens::class)
            ->callTableAction('viewKey', $tokenRecord, [
                'password' => 'secret-password-123',
            ])
            ->assertNotified("Token API: {$tokenRecord->name}");
    }

    public function test_view_key_shows_warning_for_legacy_token_without_encrypted_token(): void
    {
        $admin = User::factory()->create([
            'user_type' => 'admin',
            'password' => Hash::make('secret-password-123'),
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin);

        $legacyToken = PersonalAccessToken::query()->create([
            'tokenable_type' => User::class,
            'tokenable_id' => $admin->id,
            'name' => 'legacy-token',
            'token' => hash('sha256', 'legacy-plain-token'),
            'abilities' => ['*'],
            'authorized_url' => 'https://legacy.test.com',
            'encrypted_token' => null,
        ]);

        Livewire::test(ListApiTokens::class)
            ->callTableAction('viewKey', $legacyToken, [
                'password' => 'secret-password-123',
            ])
            ->assertNotified('Token no recuperable');
    }
}
