<?php

namespace Tests\Feature;

use App\Services\Salesforce\SalesforceService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Tests\TestCase;
use App\Models\SiteSetting;

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
            'issued_at' => (string)($issuedAtSeconds * 1000),
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
            'issued_at' => (string)($issuedAtSeconds * 1000),
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
            'issued_at' => (string)($issuedAtSeconds * 1000),
        ];
        Cache::put('forrest_token', encrypt($tokenData));

        Forrest::shouldReceive('hasToken')->once()->andReturn(true);

        $service = app(SalesforceService::class);
        
        $result = $service->executeWithTokenProtection(function () {
            return 'success_result';
        });

        $this->assertEquals('success_result', $result);
    }
}
