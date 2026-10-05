<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactSubmissions\ContactSubmissions\ContactSubmissionResource;
use App\Jobs\CreateSalesforceCaseJob;
use App\Models\ContactSubmission;
use App\Models\User;
use App\Services\Salesforce\SalesforceCaseMapper;
use App\Services\Salesforce\SalesforceService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ContactSubmissionSalesforceUpdateSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_updates_lead_when_submission_already_has_salesforce_case_id(): void
    {
        config()->set('services.salesforce.lead_enabled', true);

        $submission = ContactSubmission::query()->create([
            'name' => 'Juan Perez',
            'email' => 'juan@example.com',
            'phone' => '+56912345678',
            'fields' => ['source' => 'web'],
            'salesforce_case_id' => '00Q123456789012AAA',
            'submitted_at' => now(),
        ]);

        $mapper = Mockery::mock(SalesforceCaseMapper::class);
        $mapper->shouldReceive('mapLead')
            ->once()
            ->andReturn([
                'FirstName' => 'Juan',
                'LastName' => 'Perez',
                'Email' => 'juan@example.com',
            ]);

        $service = Mockery::mock(SalesforceService::class)->makePartial();
        $service->shouldReceive('tryAutoReconnect')->andReturn(true);
        $service->shouldReceive('updateLead')
            ->once()
            ->with('00Q123456789012AAA', Mockery::type('array'))
            ->andReturn(['id' => '00Q123456789012AAA', 'success' => true]);
        $service->shouldReceive('createLead')->never();

        $job = new CreateSalesforceCaseJob($submission, 'manual');
        $job->handle($service, $mapper);

        $submission->refresh();

        $this->assertSame('00Q123456789012AAA', $submission->salesforce_case_id);
        $this->assertNull($submission->salesforce_case_error);
        $this->assertNotNull($submission->salesforce_synced_at);
        $this->assertSame('manual', $submission->salesforce_sync_trigger);
    }

    public function test_job_creates_lead_when_submission_has_no_salesforce_case_id(): void
    {
        config()->set('services.salesforce.lead_enabled', true);

        $submission = ContactSubmission::query()->create([
            'name' => 'Maria Gonzalez',
            'email' => 'maria@example.com',
            'phone' => '+56987654321',
            'fields' => ['source' => 'web'],
            'salesforce_case_id' => null,
            'submitted_at' => now(),
        ]);

        $mapper = Mockery::mock(SalesforceCaseMapper::class);
        $mapper->shouldReceive('mapLead')
            ->once()
            ->andReturn([
                'FirstName' => 'Maria',
                'LastName' => 'Gonzalez',
                'Email' => 'maria@example.com',
            ]);

        $service = Mockery::mock(SalesforceService::class)->makePartial();
        $service->shouldReceive('tryAutoReconnect')->andReturn(true);
        $service->shouldReceive('updateLead')->never();
        $service->shouldReceive('createLead')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn(['id' => '00Q987654321098BBB', 'success' => true]);

        $job = new CreateSalesforceCaseJob($submission, 'manual');
        $job->handle($service, $mapper);

        $submission->refresh();

        $this->assertSame('00Q987654321098BBB', $submission->salesforce_case_id);
        $this->assertNull($submission->salesforce_case_error);
        $this->assertNotNull($submission->salesforce_synced_at);
    }

    public function test_job_falls_back_to_create_lead_when_update_lead_fails_with_not_found(): void
    {
        config()->set('services.salesforce.lead_enabled', true);

        $submission = ContactSubmission::query()->create([
            'name' => 'Carlos Lopez',
            'email' => 'carlos@example.com',
            'fields' => ['source' => 'web'],
            'salesforce_case_id' => '00Qdeleted123456',
            'submitted_at' => now(),
        ]);

        $mapper = Mockery::mock(SalesforceCaseMapper::class);
        $mapper->shouldReceive('mapLead')
            ->once()
            ->andReturn([
                'FirstName' => 'Carlos',
                'LastName' => 'Lopez',
                'Email' => 'carlos@example.com',
            ]);

        $service = Mockery::mock(SalesforceService::class)->makePartial();
        $service->shouldReceive('tryAutoReconnect')->andReturn(true);
        $service->shouldReceive('updateLead')
            ->once()
            ->with('00Qdeleted123456', Mockery::type('array'))
            ->andThrow(new Exception('ENTITY_IS_DELETED: entity is deleted'));

        $service->shouldReceive('createLead')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn(['id' => '00Qbrandnew78901', 'success' => true]);

        $job = new CreateSalesforceCaseJob($submission, 'manual');
        $job->handle($service, $mapper);

        $submission->refresh();

        $this->assertSame('00Qbrandnew78901', $submission->salesforce_case_id);
        $this->assertNull($submission->salesforce_case_error);
    }

    public function test_can_edit_allows_admin_and_marketing_even_when_synced(): void
    {
        $submission = ContactSubmission::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'fields' => ['source' => 'web'],
            'salesforce_case_id' => '00Qsynced12345678',
            'salesforce_case_error' => null,
            'submitted_at' => now(),
        ]);

        $admin = User::factory()->make(['user_type' => 'admin']);
        $this->actingAs($admin);
        $this->assertTrue(ContactSubmissionResource::canEdit($submission));

        $marketing = User::factory()->make(['user_type' => 'marketing']);
        $this->actingAs($marketing);
        $this->assertTrue(ContactSubmissionResource::canEdit($submission));
    }
}
