<?php

namespace Tests\Feature;

use App\Models\Broker;
use App\Models\Proyecto;
use App\Models\SalesforceOpportunity;
use App\Models\SiteSetting;
use App\Services\Salesforce\SalesforceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Tests\TestCase;

class SalesforceOpportunitySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_incremental_opportunities_into_local_snapshots(): void
    {
        SiteSetting::current();
        Broker::query()->create([
            'salesforce_id' => 'a0u123456789ABC',
            'display_name' => 'Broker local',
            'is_active' => true,
        ]);
        Proyecto::query()->create([
            'salesforce_id' => 'a0J123456789ABC',
            'name' => 'Proyecto local',
            'slug' => 'proyecto-local',
            'is_active' => true,
        ]);

        Forrest::shouldReceive('query')
            ->once()
            ->andReturn([
                'records' => [[
                    'Id' => '006123456789ABC',
                    'Name' => 'Oportunidad 1',
                    'Broker__c' => 'a0u123456789ABC',
                    'Broker__r' => ['Name' => 'AGORA INMOBILIARIO'],
                    'Proyecto__c' => 'a0J123456789ABC',
                    'Proyecto__r' => ['Name' => 'Edificio Demo'],
                    'StageName' => 'Cotización',
                    'ForecastCategoryName' => 'Oportunidades en curso',
                    'IsWon' => false,
                    'IsClosed' => false,
                    'IsDeleted' => false,
                    'IsPrivate' => false,
                    'CreatedDate' => '2026-05-11T10:00:00.000+0000',
                    'LastModifiedDate' => '2026-05-11T10:05:00.000+0000',
                    'SystemModstamp' => '2026-05-11T10:10:00.000+0000',
                    'CloseDate' => '2026-06-10',
                    'Amount' => 12345.67,
                    'CurrencyIsoCode' => 'CLF',
                    'Probability' => 10,
                    'AccountId' => '001123456789ABC',
                    'ContactId' => '003123456789ABC',
                    'OwnerId' => '005123456789ABC',
                ]],
                'done' => true,
                'totalSize' => 1,
            ]);

        $result = app(SalesforceService::class)->syncOpportunitiesIncrementally(limit: 1000);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertDatabaseHas('salesforce_opportunities', [
            'salesforce_id' => '006123456789ABC',
            'broker_salesforce_id' => 'a0u123456789ABC',
            'proyecto_salesforce_id' => 'a0J123456789ABC',
            'stage_name' => 'Cotización',
            'forecast_category_name' => 'Oportunidades en curso',
            'is_closed' => false,
            'is_won' => false,
        ]);

        $snapshot = SalesforceOpportunity::query()->firstOrFail();
        $this->assertNotNull($snapshot->broker_id);
        $this->assertNotNull($snapshot->proyecto_id);
        $this->assertSame('AGORA INMOBILIARIO', $snapshot->broker_name);
        $this->assertSame('Edificio Demo', $snapshot->proyecto_name);

        $this->assertDatabaseHas('brokers', [
            'salesforce_id' => 'a0u123456789ABC',
            'opportunities_total' => 1,
            'opportunities_open' => 1,
            'opportunities_won' => 0,
            'opportunities_total_30d' => 1,
            'opportunities_won_30d' => 0,
        ]);

        $settings = SiteSetting::current()->fresh();
        $this->assertSame(
            '2026-05-11T10:10:00+00:00',
            data_get($settings->extra_settings, 'salesforce_sync.opportunities_last_system_modstamp')
        );
    }

    public function test_it_updates_existing_snapshots_instead_of_creating_duplicates(): void
    {
        SiteSetting::current();

        SalesforceOpportunity::query()->create([
            'salesforce_id' => '006123456789ABC',
            'name' => 'Anterior',
            'is_closed' => false,
            'is_won' => false,
            'is_deleted' => false,
            'is_private' => false,
        ]);

        Forrest::shouldReceive('query')
            ->once()
            ->andReturn([
                'records' => [[
                    'Id' => '006123456789ABC',
                    'Name' => 'Actualizada',
                    'Proyecto__c' => 'a0J123456789ABC',
                    'Proyecto__r' => ['Name' => 'Edificio Demo'],
                    'StageName' => 'Cerrada ganada',
                    'ForecastCategoryName' => 'Closed',
                    'IsWon' => true,
                    'IsClosed' => true,
                    'IsDeleted' => false,
                    'IsPrivate' => false,
                    'CreatedDate' => '2026-05-11T10:00:00.000+0000',
                    'LastModifiedDate' => '2026-05-11T11:05:00.000+0000',
                    'SystemModstamp' => '2026-05-11T11:10:00.000+0000',
                    'CloseDate' => '2026-05-11',
                    'Amount' => 5000,
                    'Probability' => 100,
                    'OwnerId' => '005123456789ABC',
                ]],
                'done' => true,
                'totalSize' => 1,
            ]);

        $result = app(SalesforceService::class)->syncOpportunitiesIncrementally(limit: 1000);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseCount('salesforce_opportunities', 1);
        $this->assertDatabaseHas('salesforce_opportunities', [
            'salesforce_id' => '006123456789ABC',
            'name' => 'Actualizada',
            'is_closed' => true,
            'is_won' => true,
        ]);
    }

    public function test_it_updates_broker_metrics_by_name_when_salesforce_broker_id_is_missing(): void
    {
        $broker = Broker::query()->create([
            'display_name' => 'AGORA INMOBILIARIO',
            'is_active' => true,
        ]);

        SalesforceOpportunity::query()->create([
            'salesforce_id' => '006AAA0000000001',
            'broker_salesforce_id' => null,
            'broker_name' => 'AGORA INMOBILIARIO',
            'name' => 'Opp sin broker id 1',
            'is_closed' => false,
            'is_won' => false,
            'is_deleted' => false,
            'is_private' => false,
            'amount' => 1500,
            'salesforce_created_at' => now()->subDays(5),
            'salesforce_system_modstamp' => now()->subDays(5),
            'synced_at' => now(),
        ]);

        SalesforceOpportunity::query()->create([
            'salesforce_id' => '006AAA0000000002',
            'broker_salesforce_id' => null,
            'broker_name' => 'AGORA INMOBILIARIO',
            'name' => 'Opp sin broker id 2',
            'is_closed' => true,
            'is_won' => true,
            'is_deleted' => false,
            'is_private' => false,
            'amount' => 3500,
            'salesforce_created_at' => now()->subDays(2),
            'salesforce_system_modstamp' => now()->subDays(2),
            'synced_at' => now(),
        ]);

        app(SalesforceService::class)->syncBrokerCommercialMetricsFromSnapshots();

        $this->assertDatabaseHas('brokers', [
            'id' => $broker->id,
            'opportunities_total' => 2,
            'opportunities_open' => 1,
            'opportunities_won' => 1,
            'opportunities_lost' => 0,
            'opportunities_total_30d' => 2,
            'opportunities_won_30d' => 1,
        ]);
    }

    public function test_it_merges_name_based_metrics_into_broker_with_salesforce_id(): void
    {
        $broker = Broker::query()->create([
            'salesforce_id' => 'a0u123456789ABC',
            'display_name' => 'AGORA INMOBILIARIO',
            'is_active' => true,
        ]);

        SalesforceOpportunity::query()->create([
            'salesforce_id' => '006AAA0000001001',
            'broker_salesforce_id' => 'a0u123456789ABC',
            'broker_name' => 'AGORA INMOBILIARIO',
            'name' => 'Opp con broker id',
            'is_closed' => false,
            'is_won' => false,
            'is_deleted' => false,
            'is_private' => false,
            'amount' => 1000,
            'salesforce_created_at' => now()->subDays(8),
            'salesforce_system_modstamp' => now()->subDays(8),
            'synced_at' => now(),
        ]);

        SalesforceOpportunity::query()->create([
            'salesforce_id' => '006AAA0000001002',
            'broker_salesforce_id' => null,
            'broker_name' => 'AGORA INMOBILIARIO',
            'name' => 'Opp solo por nombre',
            'is_closed' => true,
            'is_won' => true,
            'is_deleted' => false,
            'is_private' => false,
            'amount' => 2000,
            'salesforce_created_at' => now()->subDays(4),
            'salesforce_system_modstamp' => now()->subDays(4),
            'synced_at' => now(),
        ]);

        app(SalesforceService::class)->syncBrokerCommercialMetricsFromSnapshots();

        $this->assertDatabaseHas('brokers', [
            'id' => $broker->id,
            'opportunities_total' => 2,
            'opportunities_open' => 1,
            'opportunities_won' => 1,
            'opportunities_total_30d' => 2,
            'opportunities_won_30d' => 1,
        ]);
    }
}
