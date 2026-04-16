<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ContactSubmissions\ContactSubmissions\ContactSubmissionResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Plants\PlantResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminExportActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_action_is_visible_on_users_list_page(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'user_type' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(UserResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Export');
    }

    public function test_export_action_is_visible_on_payments_list_page(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'user_type' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(PaymentResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Export');
    }

    public function test_export_action_is_visible_on_plants_list_page(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'user_type' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(PlantResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Export');
    }

    public function test_export_action_is_visible_on_contact_submissions_list_page(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'user_type' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(ContactSubmissionResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Exportar Contactos');
    }
}
