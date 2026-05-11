<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\CommercialTopBrokersChartWidget;
use App\Filament\Widgets\CommercialTopOpenBrokersChartWidget;
use App\Filament\Widgets\CommercialTopWonAmountBrokersChartWidget;
use App\Models\SalesforceOpportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialBrokerChartsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_brokers_all_time_widget_renders_successfully(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);

        SalesforceOpportunity::query()->create([
            'salesforce_id' => '006CHT000000001',
            'name' => 'Opp chart 1',
            'broker_name' => 'Broker Alpha',
            'is_closed' => false,
            'is_won' => false,
            'is_deleted' => false,
            'is_private' => false,
            'salesforce_created_at' => now()->subDay(),
            'synced_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(CommercialTopBrokersChartWidget::class)
            ->assertSuccessful();
    }

    public function test_top_open_brokers_all_time_widget_renders_successfully(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);

        SalesforceOpportunity::query()->create([
            'salesforce_id' => '006CHT000000002',
            'name' => 'Opp chart 2',
            'broker_name' => 'Broker Beta',
            'is_closed' => false,
            'is_won' => false,
            'is_deleted' => false,
            'is_private' => false,
            'salesforce_created_at' => now()->subDay(),
            'synced_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(CommercialTopOpenBrokersChartWidget::class)
            ->assertSuccessful();
    }

    public function test_top_won_amount_brokers_all_time_widget_renders_successfully(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);

        SalesforceOpportunity::query()->create([
            'salesforce_id' => '006CHT000000003',
            'name' => 'Opp chart 3',
            'broker_name' => 'Broker Gamma',
            'amount' => 12345.67,
            'is_closed' => true,
            'is_won' => true,
            'is_deleted' => false,
            'is_private' => false,
            'salesforce_created_at' => now()->subDay(),
            'synced_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(CommercialTopWonAmountBrokersChartWidget::class)
            ->assertSuccessful();
    }
}
