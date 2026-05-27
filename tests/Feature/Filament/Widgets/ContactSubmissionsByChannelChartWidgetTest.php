<?php

namespace Tests\Feature\Filament\Widgets;

use App\Models\ContactChannel;
use App\Models\ContactSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactSubmissionsByChannelChartWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_renders_with_channel_distribution_data(): void
    {
        /** @var User $admin */
        $admin = User::factory()->createOne(['user_type' => 'admin']);

        $saleChannel = ContactChannel::factory()->create([
            'name' => 'Ventas',
        ]);

        $infoChannel = ContactChannel::factory()->create([
            'name' => 'Información',
        ]);

        ContactSubmission::query()->create([
            'contact_channel_id' => $saleChannel->id,
            'name' => 'Contacto A',
            'email' => 'a@example.com',
            'phone' => '123123123',
            'fields' => [],
        ]);

        ContactSubmission::query()->create([
            'contact_channel_id' => $saleChannel->id,
            'name' => 'Contacto B',
            'email' => 'b@example.com',
            'phone' => '321321321',
            'fields' => [],
        ]);

        ContactSubmission::query()->create([
            'contact_channel_id' => $infoChannel->id,
            'name' => 'Contacto C',
            'email' => 'c@example.com',
            'phone' => '456456456',
            'fields' => [],
        ]);

        $this->actingAs($admin);

        Livewire::test(\App\Filament\Widgets\ContactSubmissionsByChannelChartWidget::class)
            ->assertSuccessful();
    }
}
