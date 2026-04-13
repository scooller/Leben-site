<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\Salesforce\SalesforceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Tests\TestCase;

class SalesforceServicePlantTypesFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_builds_plant_query_with_types_from_site_settings(): void
    {
        SiteSetting::current()->update([
            'salesforce_sync_plant_types' => ['DEPARTAMENTO', 'LOCAL'],
        ]);

        Forrest::shouldReceive('query')
            ->once()
            ->withArgs(function (string $soql): bool {
                return str_contains($soql, "Tipo_Producto__c IN ('DEPARTAMENTO','LOCAL')")
                    && str_contains($soql, "Proyecto__c IN ('a0P111111111111AAA','a0P222222222222AAA')");
            })
            ->andReturn(['records' => []]);

        $plants = app(SalesforceService::class)->findPlants(0, ['a0P111111111111AAA', 'a0P222222222222AAA']);

        $this->assertSame([], $plants);
    }

    public function test_it_falls_back_to_departamento_when_configured_types_are_empty(): void
    {
        SiteSetting::current()->update([
            'salesforce_sync_plant_types' => [],
        ]);

        Forrest::shouldReceive('query')
            ->once()
            ->withArgs(function (string $soql): bool {
                return str_contains($soql, "Tipo_Producto__c IN ('DEPARTAMENTO')")
                    && str_contains($soql, "Proyecto__c IN ('a0P333333333333AAA')");
            })
            ->andReturn(['records' => []]);

        $plants = app(SalesforceService::class)->findPlants(0, ['a0P333333333333AAA']);

        $this->assertSame([], $plants);
    }

    public function test_it_returns_empty_without_query_when_no_project_ids_are_provided(): void
    {
        SiteSetting::current()->update([
            'salesforce_sync_plant_types' => ['DEPARTAMENTO', 'LOCAL'],
        ]);

        Forrest::shouldReceive('query')->never();

        $plants = app(SalesforceService::class)->findPlants(0, []);

        $this->assertSame([], $plants);
    }
}
