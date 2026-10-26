<?php

namespace Tests\Feature;

use App\Jobs\CreateSalesforceCaseJob;
use App\Models\ContactSubmission;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Services\Salesforce\SalesforceCaseMapper;
use App\Services\Salesforce\SalesforceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SalesforceSyncInactiveProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_sync_is_skipped_for_inactive_project_when_switch_is_disabled(): void
    {
        config()->set('services.salesforce.lead_enabled', true);

        // Desactivar sincronización de proyectos inactivos
        SiteSetting::current()->update([
            'extra_settings' => [
                'salesforce_sync_inactive_projects' => false,
            ],
        ]);

        $inactiveProject = Proyecto::query()->create([
            'salesforce_id' => 'a01000000000001AAA',
            'name' => 'Nórdico',
            'slug' => 'nordico',
            'is_active' => false,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Pedro Pascal',
            'email' => 'pedro@example.com',
            'phone' => '+56912345678',
            'fields' => [
                'proyecto' => 'Nórdico',
                'source' => 'web',
            ],
            'submitted_at' => now(),
        ]);

        $service = Mockery::mock(SalesforceService::class);
        $service->shouldReceive('createLead')->never();
        $service->shouldReceive('updateLead')->never();

        $mapper = app(SalesforceCaseMapper::class);

        $job = new CreateSalesforceCaseJob($submission, 'automatic');
        $job->handle($service, $mapper);

        $submission->refresh();

        $this->assertNull($submission->salesforce_case_id);
        $this->assertSame('Omitido: Proyecto inactivo (sincronización automática desactivada).', $submission->salesforce_case_error);
        $this->assertNotNull($submission->salesforce_synced_at);
        $this->assertSame('automatic', $submission->salesforce_sync_trigger);
    }

    public function test_automatic_sync_proceeds_for_inactive_project_when_switch_is_enabled_by_default(): void
    {
        config()->set('services.salesforce.lead_enabled', true);

        // Switch por defecto activo (true)
        SiteSetting::current()->update([
            'extra_settings' => [
                'salesforce_sync_inactive_projects' => true,
            ],
        ]);

        $inactiveProject = Proyecto::query()->create([
            'salesforce_id' => 'a01000000000001AAA',
            'name' => 'Nórdico',
            'slug' => 'nordico',
            'is_active' => false,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Pedro Pascal',
            'email' => 'pedro@example.com',
            'phone' => '+56912345678',
            'fields' => [
                'proyecto' => 'Nórdico',
                'source' => 'web',
            ],
            'submitted_at' => now(),
        ]);

        $service = Mockery::mock(SalesforceService::class);
        $service->shouldReceive('tryAutoReconnect')->andReturn(true);
        $service->shouldReceive('createLead')
            ->once()
            ->andReturn(['id' => '00Qnordico12345678', 'success' => true]);

        $mapper = app(SalesforceCaseMapper::class);

        $job = new CreateSalesforceCaseJob($submission, 'automatic');
        $job->handle($service, $mapper);

        $submission->refresh();

        $this->assertSame('00Qnordico12345678', $submission->salesforce_case_id);
        $this->assertNull($submission->salesforce_case_error);
        $this->assertSame('automatic', $submission->salesforce_sync_trigger);
    }

    public function test_automatic_sync_proceeds_for_active_project_even_if_switch_is_disabled(): void
    {
        config()->set('services.salesforce.lead_enabled', true);

        SiteSetting::current()->update([
            'extra_settings' => [
                'salesforce_sync_inactive_projects' => false,
            ],
        ]);

        $activeProject = Proyecto::query()->create([
            'salesforce_id' => 'a01000000000002AAA',
            'name' => 'Edificio Activo',
            'slug' => 'edificio-activo',
            'is_active' => true,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Ana Gomez',
            'email' => 'ana@example.com',
            'phone' => '+56912345678',
            'fields' => [
                'proyecto' => 'Edificio Activo',
                'source' => 'web',
            ],
            'submitted_at' => now(),
        ]);

        $service = Mockery::mock(SalesforceService::class);
        $service->shouldReceive('tryAutoReconnect')->andReturn(true);
        $service->shouldReceive('createLead')
            ->once()
            ->andReturn(['id' => '00Qactivo12345678', 'success' => true]);

        $mapper = app(SalesforceCaseMapper::class);

        $job = new CreateSalesforceCaseJob($submission, 'automatic');
        $job->handle($service, $mapper);

        $submission->refresh();

        $this->assertSame('00Qactivo12345678', $submission->salesforce_case_id);
        $this->assertNull($submission->salesforce_case_error);
    }

    public function test_manual_sync_proceeds_for_inactive_project_even_if_switch_is_disabled(): void
    {
        config()->set('services.salesforce.lead_enabled', true);

        SiteSetting::current()->update([
            'extra_settings' => [
                'salesforce_sync_inactive_projects' => false,
            ],
        ]);

        $inactiveProject = Proyecto::query()->create([
            'salesforce_id' => 'a01000000000003AAA',
            'name' => 'Nórdico',
            'slug' => 'nordico',
            'is_active' => false,
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Pedro Pascal',
            'email' => 'pedro@example.com',
            'phone' => '+56912345678',
            'fields' => [
                'proyecto' => 'Nórdico',
                'source' => 'web',
            ],
            'submitted_at' => now(),
        ]);

        $service = Mockery::mock(SalesforceService::class);
        $service->shouldReceive('tryAutoReconnect')->andReturn(true);
        $service->shouldReceive('createLead')
            ->once()
            ->andReturn(['id' => '00Qmanual12345678', 'success' => true]);

        $mapper = app(SalesforceCaseMapper::class);

        // Disparado manualmente por un admin
        $job = new CreateSalesforceCaseJob($submission, 'manual');
        $job->handle($service, $mapper);

        $submission->refresh();

        $this->assertSame('00Qmanual12345678', $submission->salesforce_case_id);
        $this->assertNull($submission->salesforce_case_error);
        $this->assertSame('manual', $submission->salesforce_sync_trigger);
    }
}
