<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\CommercialPipelineStatsWidget;
use App\Models\SalesforceOpportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialPipelineStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_displays_broker_coverage_and_unassigned_stats(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);

        SalesforceOpportunity::query()->create([
            'salesforce_id' => '006TST000000001',
            'name' => 'Opp con broker',
            'broker_salesforce_id' => 'a0uTST000000001',
            'broker_name' => 'Broker Uno',
            'proyecto_name' => 'Proyecto Uno',
            'is_closed' => false,
            'is_won' => false,
            'is_deleted' => false,
            'is_private' => false,
            'salesforce_created_at' => now()->subDay(),
            'synced_at' => now(),
        ]);

        SalesforceOpportunity::query()->create([
            'salesforce_id' => '006TST000000002',
            'name' => 'Opp sin broker',
            'broker_salesforce_id' => null,
            'broker_name' => null,
            'proyecto_name' => 'Proyecto Dos',
            'is_closed' => false,
            'is_won' => false,
            'is_deleted' => false,
            'is_private' => false,
            'salesforce_created_at' => now()->subDay(),
            'synced_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(CommercialPipelineStatsWidget::class)
            ->assertSee('Sin broker asignado (30d)')
            ->assertSee('Cobertura broker (30d)')
            ->assertSee('50.00%')
            ->assertSee('1 de 2 oportunidades');
    }
}
