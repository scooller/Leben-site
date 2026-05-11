<?php

namespace Tests\Feature;

use App\Filament\Actions\SyncBrokersAction;
use App\Models\Broker;
use App\Services\Salesforce\SalesforceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncBrokersActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_brokers_creates_new_brokers(): void
    {
        $this->mock(SalesforceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findBrokers')
                ->once()
                ->andReturn([
                    [
                        'id' => 'SF-BRK-001',
                        'name' => 'AGORA INMOBILIARIO',
                        'email' => 'francisco.navarro@agorainmobiliario.com',
                        'phone' => null,
                    ],
                ]);
            $mock->shouldReceive('syncBrokerCommercialMetricsFromSnapshots')
                ->once();
        });

        $result = SyncBrokersAction::execute();

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertDatabaseHas('brokers', [
            'salesforce_id' => 'SF-BRK-001',
            'display_name' => 'AGORA INMOBILIARIO',
            'contact_email' => 'francisco.navarro@agorainmobiliario.com',
        ]);
    }

    public function test_sync_brokers_updates_existing_brokers(): void
    {
        Broker::factory()->create([
            'salesforce_id' => 'SF-BRK-002',
            'display_name' => 'Nombre Anterior',
            'contact_email' => 'old@example.com',
        ]);

        $this->mock(SalesforceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findBrokers')
                ->once()
                ->andReturn([
                    [
                        'id' => 'SF-BRK-002',
                        'name' => 'Nombre Actualizado',
                        'email' => 'nuevo@example.com',
                        'phone' => '+56912345678',
                    ],
                ]);
            $mock->shouldReceive('syncBrokerCommercialMetricsFromSnapshots')
                ->once();
        });

        $result = SyncBrokersAction::execute();

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseHas('brokers', [
            'salesforce_id' => 'SF-BRK-002',
            'display_name' => 'Nombre Actualizado',
            'contact_email' => 'nuevo@example.com',
            'contact_phone' => '+56912345678',
        ]);
    }

    public function test_sync_brokers_handles_empty_results(): void
    {
        $this->mock(SalesforceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findBrokers')
                ->once()
                ->andReturn([]);
            $mock->shouldNotReceive('syncBrokerCommercialMetricsFromSnapshots');
        });

        $result = SyncBrokersAction::execute();

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['count']);
    }

    public function test_sync_brokers_skips_entries_without_salesforce_id(): void
    {
        $this->mock(SalesforceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findBrokers')
                ->once()
                ->andReturn([
                    ['id' => '', 'name' => 'Sin ID', 'email' => 'sin@id.com', 'phone' => null],
                    ['id' => 'SF-BRK-003', 'name' => 'Con ID', 'email' => 'con@id.com', 'phone' => null],
                ]);
            $mock->shouldReceive('syncBrokerCommercialMetricsFromSnapshots')
                ->once();
        });

        $result = SyncBrokersAction::execute();

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['created']);
        $this->assertDatabaseMissing('brokers', ['display_name' => 'Sin ID']);
        $this->assertDatabaseHas('brokers', ['salesforce_id' => 'SF-BRK-003']);
    }

    public function test_sync_brokers_includes_entries_without_email_and_phone(): void
    {
        $this->mock(SalesforceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findBrokers')
                ->once()
                ->andReturn([
                    ['id' => 'SF-BRK-NOEMPTY', 'name' => 'Sin contacto', 'email' => null, 'phone' => null],
                    ['id' => 'SF-BRK-HASEMAIL', 'name' => 'Con email', 'email' => 'broker@example.com', 'phone' => null],
                    ['id' => 'SF-BRK-HASPHONE', 'name' => 'Con telefono', 'email' => null, 'phone' => '+56911111111'],
                ]);
            $mock->shouldReceive('syncBrokerCommercialMetricsFromSnapshots')
                ->once();
        });

        $result = SyncBrokersAction::execute();

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['created']);
        $this->assertDatabaseHas('brokers', ['salesforce_id' => 'SF-BRK-NOEMPTY']);
        $this->assertDatabaseHas('brokers', ['salesforce_id' => 'SF-BRK-HASEMAIL']);
        $this->assertDatabaseHas('brokers', ['salesforce_id' => 'SF-BRK-HASPHONE']);
    }
}
