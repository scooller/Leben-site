<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Tests\TestCase;

class SalesforceOAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_persists_oauth_connection_metadata_on_success(): void
    {
        Cache::put(config('forrest.storage.path', 'forrest_').'version', ['version' => '58.0']);
        Cache::put(config('forrest.storage.path', 'forrest_').'resources', ['sobjects' => '/services/data/v58.0/sobjects']);

        Forrest::shouldReceive('callback')->once()->andReturnNull();

        $response = $this->get(route('salesforce.callback'));

        $response->assertRedirect('/admin/site-settings');
        $response->assertSessionHasNoErrors();

        $settings = SiteSetting::current()->fresh();
        $extraSettings = is_array($settings?->extra_settings) ? $settings->extra_settings : [];

        $this->assertTrue((bool) data_get($extraSettings, 'salesforce_oauth.connected'));
        $this->assertIsString(data_get($extraSettings, 'salesforce_oauth.last_connected_at'));
        $this->assertNotSame('', trim((string) data_get($extraSettings, 'salesforce_oauth.last_connected_at')));
        $this->assertSame((string) config('forrest.authentication', ''), data_get($extraSettings, 'salesforce_oauth.auth_method'));
        $this->assertSame(['version' => '58.0'], data_get($extraSettings, 'salesforce_oauth.version_cache_backup'));
        $this->assertSame(['sobjects' => '/services/data/v58.0/sobjects'], data_get($extraSettings, 'salesforce_oauth.resources_cache_backup'));

        $this->assertTrue(Cache::has('salesforce_oauth_just_connected'));
    }

    public function test_callback_redirects_with_error_when_salesforce_returns_error_params(): void
    {
        $response = $this->get(route('salesforce.callback', [
            'error' => 'access_denied',
            'error_description' => 'Access denied',
        ]));

        $response->assertRedirect('/admin/site-settings');
        $response->assertSessionHasErrors(['salesforce']);
    }
}
