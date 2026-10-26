<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\Salesforce\SalesforceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Tests\TestCase;

class SalesforceProactiveRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed initial site setting
        SiteSetting::create([
            'site_name' => 'Test',
            'extra_settings' => [],
        ]);

        Cache::flush();
    }

    public function test_is_token_expiring_soon_returns_true_when_close_to_expiry()
    {
        // Token emitted 1 hour and 55 minutes ago (115 minutes ago = 6900 seconds)
        // Salesforce token expires in 2 hours (7200 seconds)
        $issuedAtSeconds = time() - 6900;

        $tokenData = [
            'access_token' => 'test_token',
            'refresh_token' => 'test_refresh',
            'issued_at' => (string) ($issuedAtSeconds * 1000),
        ];

        Cache::put('forrest_token', encrypt($tokenData));

        $service = app(SalesforceService::class);

        // Threshold is 900 seconds (15 mins), remaining time is 300 seconds
        $this->assertTrue($service->isTokenExpiringSoon(900));
    }

    public function test_is_token_expiring_soon_returns_false_when_far_from_expiry()
    {
        // Token emitted 10 minutes ago
        $issuedAtSeconds = time() - 600;

        $tokenData = [
            'access_token' => 'test_token',
            'refresh_token' => 'test_refresh',
            'issued_at' => (string) ($issuedAtSeconds * 1000),
        ];

        Cache::put('forrest_token', encrypt($tokenData));

        $service = app(SalesforceService::class);

        // Threshold is 900 seconds (15 mins), remaining time is 6600 seconds
        $this->assertFalse($service->isTokenExpiringSoon(900));
    }

    public function test_execute_with_token_protection_returns_result_successfully()
    {
        // Far from expiry
        $issuedAtSeconds = time() - 600;
        $tokenData = [
            'access_token' => 'test_token',
            'refresh_token' => 'test_refresh',
            'issued_at' => (string) ($issuedAtSeconds * 1000),
        ];
        Cache::put('forrest_token', encrypt($tokenData));

        Forrest::shouldReceive('hasToken')->once()->andReturn(true);

        $service = app(SalesforceService::class);

        $result = $service->executeWithTokenProtection(function () {
            return 'success_result';
        });

        $this->assertEquals('success_result', $result);
    }

    public function test_ensure_resources_loaded_restores_from_db_backup(): void
    {
        $siteSettings = SiteSetting::current();
        $siteSettings->update([
            'extra_settings' => [
                'salesforce_oauth' => [
                    'version_cache_backup' => ['version' => '58.0'],
                    'resources_cache_backup' => ['sobjects' => '/services/data/v58.0/sobjects'],
                ],
            ],
        ]);

        $cachePath = config('forrest.storage.path', 'forrest_');
        Cache::forget($cachePath.'version');
        Cache::forget($cachePath.'resources');

        $service = app(SalesforceService::class);
        $service->ensureResourcesLoaded();

        $this->assertSame(['version' => '58.0'], Cache::get($cachePath.'version'));
        $this->assertSame(['sobjects' => '/services/data/v58.0/sobjects'], Cache::get($cachePath.'resources'));
    }

    public function test_ensure_resources_loaded_fetches_from_forrest_when_no_backup(): void
    {
        $cachePath = config('forrest.storage.path', 'forrest_');
        Cache::forget($cachePath.'version');
        Cache::forget($cachePath.'resources');

        Forrest::shouldReceive('versions')
            ->once()
            ->andReturn([
                ['version' => '57.0', 'url' => '/services/data/v57.0'],
                ['version' => '58.0', 'url' => '/services/data/v58.0'],
            ]);

        Forrest::shouldReceive('resources')
            ->once()
            ->with(['format' => 'json'])
            ->andReturn(['sobjects' => '/services/data/v58.0/sobjects']);

        $service = app(SalesforceService::class);
        $service->ensureResourcesLoaded();

        $this->assertSame(['version' => '57.0', 'url' => '/services/data/v57.0'], Cache::get($cachePath.'version'));
        $this->assertSame(['sobjects' => '/services/data/v58.0/sobjects'], Cache::get($cachePath.'resources'));
    }

    public function test_execute_with_token_protection_recovers_from_missing_resource_exception(): void
    {
        $issuedAtSeconds = time() - 600;
        $tokenData = [
            'access_token' => 'test_token',
            'refresh_token' => 'test_refresh',
            'issued_at' => (string) ($issuedAtSeconds * 1000),
        ];
        Cache::put('forrest_token', encrypt($tokenData));

        $siteSettings = SiteSetting::current();
        $siteSettings->update([
            'extra_settings' => [
                'salesforce_oauth' => [
                    'version_cache_backup' => ['version' => '58.0'],
                    'resources_cache_backup' => ['sobjects' => '/services/data/v58.0/sobjects'],
                ],
            ],
        ]);

        Forrest::shouldReceive('hasToken')->once()->andReturn(true);

        $service = app(SalesforceService::class);

        $attempts = 0;
        $result = $service->executeWithTokenProtection(function () use (&$attempts) {
            $attempts++;
            if ($attempts === 1) {
                throw new \Omniphx\Forrest\Exceptions\MissingResourceException('No resources available');
            }

            return 'recovered_after_missing_resource';
        });

        $this->assertSame('recovered_after_missing_resource', $result);
        $this->assertSame(2, $attempts);
    }
}
