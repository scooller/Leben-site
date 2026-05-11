<?php

namespace Tests\Feature\Console;

use App\Services\Salesforce\SalesforceService;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncBrokerCommercialMetricsCommandTest extends TestCase
{
    public function test_command_recalculates_broker_metrics(): void
    {
        $this->mock(SalesforceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('syncBrokerCommercialMetricsFromSnapshots')
                ->once();
        });

        $this->artisan('salesforce:sync-broker-metrics')
            ->expectsOutput('Recalculando métricas comerciales de brokers...')
            ->expectsOutput('Métricas comerciales de brokers actualizadas correctamente.')
            ->assertSuccessful();
    }
}
