<?php

namespace Tests\Feature\Console;

use App\Services\Salesforce\SalesforceService;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncSalesforceOpportunitiesCommandTest extends TestCase
{
    public function test_command_runs_opportunity_sync_and_prints_summary(): void
    {
        $this->mock(SalesforceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('syncOpportunitiesIncrementally')
                ->once()
                ->with(null, 2000)
                ->andReturn([
                    'success' => true,
                    'message' => 'Sincronizacion de oportunidades completada. 1 creadas, 2 actualizadas.',
                    'synced' => 3,
                    'created' => 1,
                    'updated' => 2,
                    'watermark' => '2026-05-11T14:00:00+00:00',
                ]);
        });

        $this->artisan('salesforce:sync-opportunities')
            ->expectsOutput('Iniciando sincronizacion de oportunidades Salesforce...')
            ->expectsOutput('Sincronizacion de oportunidades completada. 1 creadas, 2 actualizadas.')
            ->expectsOutput('Synced: 3')
            ->expectsOutput('Created: 1')
            ->expectsOutput('Updated: 2')
            ->expectsOutput('Watermark: 2026-05-11T14:00:00+00:00')
            ->assertSuccessful();
    }

    public function test_command_validates_since_option(): void
    {
        $this->mock(SalesforceService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('syncOpportunitiesIncrementally');
        });

        $this->artisan('salesforce:sync-opportunities --since=not-a-date')
            ->expectsOutput('Parametro --since invalido. Usa formato ISO8601, por ejemplo: 2026-05-01T00:00:00Z')
            ->assertFailed();
    }
}
