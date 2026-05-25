<?php

namespace Tests\Feature\Filament\Widgets;

use App\Models\ContactChannel;
use App\Models\ContactSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactSubmissionsStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_displays_contact_statistics(): void
    {
        /** @var User $admin */
        $admin = User::factory()->createOne(['user_type' => 'admin']);
        $channel = ContactChannel::factory()->create();

        ContactSubmission::query()->create([
            'contact_channel_id' => $channel->id,
            'name' => 'Contacto A',
            'email' => 'a@example.com',
            'phone' => '123456789',
            'fields' => [],
            'salesforce_case_id' => '00Q000000000001AAA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ContactSubmission::query()->create([
            'contact_channel_id' => $channel->id,
            'name' => 'Contacto B',
            'email' => 'b@example.com',
            'phone' => '987654321',
            'fields' => [],
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        ContactSubmission::query()->create([
            'contact_channel_id' => $channel->id,
            'name' => 'Contacto C',
            'email' => 'c@example.com',
            'phone' => '111222333',
            'fields' => [],
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $this->actingAs($admin);

        Livewire::test(\App\Filament\Widgets\ContactSubmissionsStatsWidget::class)
            ->assertSuccessful()
            ->assertSee('Total contactos')
            ->assertSee('3')
            ->assertSee('Contactos hoy')
            ->assertSee('1')
            ->assertSee('Últimos 7 días')
            ->assertSee('2')
            ->assertSee('Sincronizados SF');
    }
}
