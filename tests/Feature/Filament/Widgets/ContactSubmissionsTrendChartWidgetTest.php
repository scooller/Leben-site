<?php

namespace Tests\Feature\Filament\Widgets;

use App\Models\ContactChannel;
use App\Models\ContactSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactSubmissionsTrendChartWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_renders_with_contact_data(): void
    {
        /** @var User $admin */
        $admin = User::factory()->createOne(['user_type' => 'admin']);
        $channel = ContactChannel::factory()->create();

        ContactSubmission::query()->create([
            'contact_channel_id' => $channel->id,
            'name' => 'Contacto Hoy',
            'email' => 'hoy@example.com',
            'phone' => '123456789',
            'fields' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ContactSubmission::query()->create([
            'contact_channel_id' => $channel->id,
            'name' => 'Contacto Ayer',
            'email' => 'ayer@example.com',
            'phone' => '987654321',
            'fields' => [],
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->actingAs($admin);

        Livewire::test(\App\Filament\Widgets\ContactSubmissionsTrendChartWidget::class)
            ->assertSuccessful();
    }
}
