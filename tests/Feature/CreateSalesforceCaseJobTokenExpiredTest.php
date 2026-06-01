<?php

namespace Tests\Feature;

use App\Exceptions\SalesforceTokenExpiredException;
use App\Jobs\CreateSalesforceCaseJob;
use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use App\Services\Salesforce\SalesforceCaseMapper;
use App\Services\Salesforce\SalesforceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CreateSalesforceCaseJobTokenExpiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget($this->alertCacheKeyFor('invalid_grant: expired access/refresh token'));
    }

    public function test_it_marks_oauth_as_disconnected_and_updates_submission_when_token_is_expired(): void
    {
        config()->set('services.salesforce.lead_enabled', true);

        $submission = ContactSubmission::query()->create([
            'name' => 'Camila Perez',
            'email' => 'camila@example.com',
            'fields' => ['source' => 'test'],
            'submitted_at' => now(),
        ]);

        $mapper = \Mockery::mock(SalesforceCaseMapper::class);
        $mapper->shouldReceive('mapLead')
            ->once()
            ->withArgs(function (ContactSubmission $model) use ($submission): bool {
                return $model->is($submission);
            })
            ->andReturn([
                'FirstName' => 'Camila',
                'LastName' => 'Perez',
                'Email' => 'camila@example.com',
            ]);

        $service = \Mockery::mock(SalesforceService::class)->makePartial();
        $service->shouldReceive('tryAutoReconnect')
            ->once()
            ->andReturn(true);
        $service->shouldReceive('createLead')
            ->once()
            ->andThrow(new SalesforceTokenExpiredException);

        $job = new CreateSalesforceCaseJob($submission);
        $job->handle($service, $mapper);

        $submission->refresh();

        $this->assertNotSame('', trim((string) $submission->salesforce_case_error));
        $this->assertNotNull($submission->salesforce_synced_at);

        $settings = SiteSetting::current()->fresh();
        $extraSettings = is_array($settings?->extra_settings) ? $settings->extra_settings : [];

        $this->assertFalse((bool) data_get($extraSettings, 'salesforce_oauth.connected', true));
        $this->assertSame('invalid_grant: expired access/refresh token', data_get($extraSettings, 'salesforce_oauth.last_error'));
        $this->assertIsString(data_get($extraSettings, 'salesforce_oauth.last_disconnected_at'));
    }

    public function test_it_throttles_salesforce_oauth_disconnection_alert_email_for_same_reason(): void
    {
        config()->set('services.salesforce.lead_enabled', true);

        SiteSetting::current()->update([
            'contact_notification_email' => 'ops@example.com',
        ]);

        $firstSubmission = ContactSubmission::query()->create([
            'name' => 'Camila Perez',
            'email' => 'camila@example.com',
            'fields' => ['source' => 'test-1'],
            'submitted_at' => now(),
        ]);

        $secondSubmission = ContactSubmission::query()->create([
            'name' => 'Pedro Soto',
            'email' => 'pedro@example.com',
            'fields' => ['source' => 'test-2'],
            'submitted_at' => now(),
        ]);

        $mapper = \Mockery::mock(SalesforceCaseMapper::class);
        $mapper->shouldReceive('mapLead')
            ->twice()
            ->andReturnUsing(function (ContactSubmission $model): array {
                return [
                    'FirstName' => explode(' ', trim((string) $model->name))[0] ?? 'Nombre',
                    'LastName' => 'Test',
                    'Email' => (string) $model->email,
                ];
            });

        $service = \Mockery::mock(SalesforceService::class)->makePartial();
        // Ambos jobs intentan tryAutoReconnect(): el primero porque no hay token en caché,
        // el segundo porque connected=false fue seteado por el primer job.
        $service->shouldReceive('tryAutoReconnect')
            ->twice()
            ->andReturn(true);
        $service->shouldReceive('createLead')
            ->twice()
            ->andThrow(new SalesforceTokenExpiredException);

        Mail::shouldReceive('raw')
            ->once();

        (new CreateSalesforceCaseJob($firstSubmission))->handle($service, $mapper);
        (new CreateSalesforceCaseJob($secondSubmission))->handle($service, $mapper);
    }

    public function test_it_skips_sync_when_oauth_is_already_marked_as_disconnected_and_autoreconnect_fails(): void
    {
        config()->set('services.salesforce.lead_enabled', true);

        SiteSetting::current()->update([
            'extra_settings' => [
                'salesforce_oauth' => [
                    'connected' => false,
                ],
            ],
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Camila Perez',
            'email' => 'camila@example.com',
            'fields' => ['source' => 'test'],
            'submitted_at' => now(),
        ]);

        $mapper = \Mockery::mock(SalesforceCaseMapper::class);
        $mapper->shouldReceive('mapLead')->never();

        // Con el nuevo comportamiento, el job intenta tryAutoReconnect() antes de rendirse.
        // Si el refresh_token ya expiró/fue revocado, la reconexión falla y el job salta.
        $service = \Mockery::mock(SalesforceService::class);
        $service->shouldReceive('tryAutoReconnect')->once()->andReturn(false);
        $service->shouldReceive('createLead')->never();

        (new CreateSalesforceCaseJob($submission))->handle($service, $mapper);

        $submission->refresh();

        $this->assertSame(
            'OAuth Salesforce desconectado. Auto-reconexión fallida. Reconectar en panel admin.',
            $submission->salesforce_case_error
        );
        $this->assertNotNull($submission->salesforce_synced_at);
    }

    private function alertCacheKeyFor(string $reason): string
    {
        return sprintf('salesforce:oauth:disconnect-alert:%s', md5(strtolower($reason)));
    }
}
