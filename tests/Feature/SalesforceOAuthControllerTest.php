<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Tests\TestCase;

class SalesforceOAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_stores_origin_url_in_session_before_redirecting_to_salesforce(): void
    {
        Forrest::shouldReceive('authenticate')
            ->once()
            ->andReturn(redirect('https://login.salesforce.com'));

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('salesforce.oauth.connect', [
                'redirect_to' => '/admin/proyectos/123/edit',
            ]));

        $response->assertRedirect('https://login.salesforce.com');
        $response->assertSessionHas('salesforce_oauth_redirect_to', '/admin/proyectos/123/edit');
    }

    public function test_callback_persists_oauth_connection_metadata_on_success_and_redirects_to_origin(): void
    {
        Forrest::shouldReceive('callback')->once()->andReturnNull();

        $response = $this
            ->withSession(['salesforce_oauth_redirect_to' => '/admin/proyectos'])
            ->get(route('salesforce.callback'));

        $response->assertRedirect('/admin/proyectos');
        $response->assertSessionHasNoErrors();

        $settings = SiteSetting::current()->fresh();
        $extraSettings = is_array($settings?->extra_settings) ? $settings->extra_settings : [];

        $this->assertTrue((bool) data_get($extraSettings, 'salesforce_oauth.connected'));
        $this->assertIsString(data_get($extraSettings, 'salesforce_oauth.last_connected_at'));
        $this->assertNotSame('', trim((string) data_get($extraSettings, 'salesforce_oauth.last_connected_at')));
        $this->assertSame((string) config('forrest.authentication', ''), data_get($extraSettings, 'salesforce_oauth.auth_method'));

        $this->assertTrue(Cache::has('salesforce_oauth_just_connected'));
    }

    public function test_callback_redirects_with_error_when_salesforce_returns_error_params(): void
    {
        $response = $this
            ->withSession(['salesforce_oauth_redirect_to' => '/admin/contact-submissions'])
            ->get(route('salesforce.callback', [
                'error' => 'access_denied',
                'error_description' => 'Access denied',
            ]));

        $response->assertRedirect('/admin/contact-submissions');
        $response->assertSessionHasErrors(['salesforce']);
    }

    public function test_callback_falls_back_to_site_settings_when_origin_is_invalid(): void
    {
        Forrest::shouldReceive('callback')->once()->andReturnNull();

        $response = $this
            ->withSession(['salesforce_oauth_redirect_to' => 'https://example.com/malicious'])
            ->get(route('salesforce.callback'));

        $response->assertRedirect('/admin/site-settings');
    }
}
