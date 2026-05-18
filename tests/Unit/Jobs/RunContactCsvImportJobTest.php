<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RunContactCsvImportJob;
use App\Models\ContactChannel;
use App\Models\ContactSubmission;
use App\Services\ContactImport\ContactImportProgressTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunContactCsvImportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_logs_key_fields_when_email_is_invalid(): void
    {
        $channel = ContactChannel::factory()->create([
            'is_active' => true,
        ]);

        $importId = 'invalid-email-test';
        $tracker = app(ContactImportProgressTracker::class);
        $tracker->initialize(
            importId: $importId,
            totalRows: 1,
            channelName: (string) $channel->name,
            syncToSalesforce: false,
            dryRun: false,
        );

        $job = new RunContactCsvImportJob(
            importId: $importId,
            rows: [[
                'Nombre' => 'Jane Doe',
                'email' => 'correo-invalido',
                'Celular' => '+56911111111',
                'COMUNA' => 'Puerto Varas',
                'Proyecto' => 'Edificio Inn',
            ]],
            mappings: [
                ['source_column' => 'Nombre', 'target_field' => 'name'],
                ['source_column' => 'email', 'target_field' => 'email'],
                ['source_column' => 'Celular', 'target_field' => 'phone'],
                ['source_column' => 'COMUNA', 'target_field' => 'fields.comuna'],
                ['source_column' => 'Proyecto', 'target_field' => 'fields.proyecto'],
            ],
            contactChannelId: $channel->id,
            autoMapUnmapped: true,
            homologateComuna: false,
            homologateProyecto: false,
            syncToSalesforce: false,
            dryRun: false,
            hasHeader: true,
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        );

        $job->handle(
            app(\App\Services\ContactImport\ContactCsvRowMapper::class),
            app(\App\Services\ContactImport\ContactTextHomologationService::class),
            $tracker,
        );

        $logs = implode("\n", $tracker->snapshot($importId)['logs']);

        $this->assertStringContainsString('email inválido.', $logs);
        $this->assertStringContainsString('Nombre: Jane Doe', $logs);
        $this->assertStringContainsString('Email: correo-invalido', $logs);
        $this->assertStringContainsString('Proyecto: Edificio Inn', $logs);
        $this->assertStringContainsString('Comuna: Puerto Varas', $logs);
    }

    public function test_handle_logs_key_fields_when_comuna_or_proyecto_is_missing(): void
    {
        $channel = ContactChannel::factory()->create([
            'is_active' => true,
        ]);

        $importId = 'missing-required-fields-test';
        $tracker = app(ContactImportProgressTracker::class);
        $tracker->initialize(
            importId: $importId,
            totalRows: 1,
            channelName: (string) $channel->name,
            syncToSalesforce: false,
            dryRun: false,
        );

        $job = new RunContactCsvImportJob(
            importId: $importId,
            rows: [[
                'Nombre' => 'Jane Doe',
                'email' => 'jane@example.com',
                'Celular' => '+56911111111',
                'COMUNA' => '',
                'Proyecto' => '',
            ]],
            mappings: [
                ['source_column' => 'Nombre', 'target_field' => 'name'],
                ['source_column' => 'email', 'target_field' => 'email'],
                ['source_column' => 'Celular', 'target_field' => 'phone'],
                ['source_column' => 'COMUNA', 'target_field' => 'fields.comuna'],
                ['source_column' => 'Proyecto', 'target_field' => 'fields.proyecto'],
            ],
            contactChannelId: $channel->id,
            autoMapUnmapped: true,
            homologateComuna: false,
            homologateProyecto: false,
            syncToSalesforce: false,
            dryRun: false,
            hasHeader: true,
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        );

        $job->handle(
            app(\App\Services\ContactImport\ContactCsvRowMapper::class),
            app(\App\Services\ContactImport\ContactTextHomologationService::class),
            $tracker,
        );

        $logs = implode("\n", $tracker->snapshot($importId)['logs']);

        $this->assertStringContainsString('Comuna y Proyecto son obligatorios.', $logs);
        $this->assertStringContainsString('Nombre: Jane Doe', $logs);
        $this->assertStringContainsString('Email: jane@example.com', $logs);
        $this->assertStringContainsString('Proyecto: -', $logs);
        $this->assertStringContainsString('Comuna: -', $logs);
    }

    public function test_handle_logs_key_fields_when_salesforce_sync_fails(): void
    {
        $channel = ContactChannel::factory()->create([
            'is_active' => true,
        ]);

        $importId = 'sync-fail-test';
        $tracker = app(ContactImportProgressTracker::class);
        $tracker->initialize(
            importId: $importId,
            totalRows: 1,
            channelName: (string) $channel->name,
            syncToSalesforce: true,
            dryRun: false,
        );

        $job = new class(importId: $importId, rows: [['Nombre' => 'Jane Doe', 'email' => 'jane@example.com', 'Celular' => '+56911111111', 'COMUNA' => 'Puerto Varas', 'Proyecto' => 'Edificio Inn']], mappings: [['source_column' => 'Nombre', 'target_field' => 'name'], ['source_column' => 'email', 'target_field' => 'email'], ['source_column' => 'Celular', 'target_field' => 'phone'], ['source_column' => 'COMUNA', 'target_field' => 'fields.comuna'], ['source_column' => 'Proyecto', 'target_field' => 'fields.proyecto']], contactChannelId: $channel->id, autoMapUnmapped: true, homologateComuna: false, homologateProyecto: false, syncToSalesforce: true, dryRun: false, hasHeader: true, ipAddress: '127.0.0.1', userAgent: 'phpunit') extends RunContactCsvImportJob
        {
            protected function dispatchSalesforceSync(ContactSubmission $submission): mixed
            {
                return 'Error simulado de Salesforce';
            }
        };

        $job->handle(
            app(\App\Services\ContactImport\ContactCsvRowMapper::class),
            app(\App\Services\ContactImport\ContactTextHomologationService::class),
            $tracker,
        );

        $logs = implode("\n", $tracker->snapshot($importId)['logs']);

        $this->assertStringContainsString('error de sync Salesforce - Error simulado de Salesforce', $logs);
        $this->assertStringContainsString('Nombre: Jane Doe', $logs);
        $this->assertStringContainsString('Email: jane@example.com', $logs);
        $this->assertStringContainsString('Proyecto: Edificio Inn', $logs);
        $this->assertStringContainsString('Comuna: Puerto Varas', $logs);
    }

    public function test_handle_in_real_mode_logs_key_fields_when_contact_is_created(): void
    {
        $channel = ContactChannel::factory()->create([
            'is_active' => true,
        ]);

        $importId = 'real-import-test';
        $tracker = app(ContactImportProgressTracker::class);
        $tracker->initialize(
            importId: $importId,
            totalRows: 1,
            channelName: (string) $channel->name,
            syncToSalesforce: false,
            dryRun: false,
        );

        $job = new RunContactCsvImportJob(
            importId: $importId,
            rows: [[
                'Nombre' => 'Jane Doe',
                'email' => 'jane@example.com',
                'Celular' => '+56911111111',
                'COMUNA' => 'Puerto Varas',
                'Proyecto' => 'Edificio Inn',
            ]],
            mappings: [
                ['source_column' => 'Nombre', 'target_field' => 'name'],
                ['source_column' => 'email', 'target_field' => 'email'],
                ['source_column' => 'Celular', 'target_field' => 'phone'],
                ['source_column' => 'COMUNA', 'target_field' => 'fields.comuna'],
                ['source_column' => 'Proyecto', 'target_field' => 'fields.proyecto'],
            ],
            contactChannelId: $channel->id,
            autoMapUnmapped: true,
            homologateComuna: false,
            homologateProyecto: false,
            syncToSalesforce: false,
            dryRun: false,
            hasHeader: true,
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        );

        $job->handle(
            app(\App\Services\ContactImport\ContactCsvRowMapper::class),
            app(\App\Services\ContactImport\ContactTextHomologationService::class),
            $tracker,
        );

        $this->assertDatabaseCount(ContactSubmission::class, 1);

        $logs = implode("\n", $tracker->snapshot($importId)['logs']);

        $this->assertStringContainsString('contacto creado.', $logs);
        $this->assertStringContainsString('Nombre: Jane Doe', $logs);
        $this->assertStringContainsString('Email: jane@example.com', $logs);
        $this->assertStringContainsString('Teléfono: +56911111111', $logs);
        $this->assertStringContainsString('Proyecto: Edificio Inn', $logs);
        $this->assertStringContainsString('Comuna: Puerto Varas', $logs);
    }

    public function test_handle_in_dry_run_mode_validates_rows_without_creating_contacts(): void
    {
        $channel = ContactChannel::factory()->create([
            'is_active' => true,
        ]);

        $importId = 'dry-run-test';
        $tracker = app(ContactImportProgressTracker::class);
        $tracker->initialize(
            importId: $importId,
            totalRows: 1,
            channelName: (string) $channel->name,
            syncToSalesforce: true,
            dryRun: true,
        );

        $job = new RunContactCsvImportJob(
            importId: $importId,
            rows: [[
                'Nombre' => 'Jane Doe',
                'email' => 'jane@example.com',
                'Celular' => '+56911111111',
                'COMUNA' => 'Puerto Varas',
                'Proyecto' => 'Edificio Inn',
            ]],
            mappings: [
                ['source_column' => 'Nombre', 'target_field' => 'name'],
                ['source_column' => 'email', 'target_field' => 'email'],
                ['source_column' => 'Celular', 'target_field' => 'phone'],
                ['source_column' => 'COMUNA', 'target_field' => 'fields.comuna'],
                ['source_column' => 'Proyecto', 'target_field' => 'fields.proyecto'],
            ],
            contactChannelId: $channel->id,
            autoMapUnmapped: true,
            homologateComuna: false,
            homologateProyecto: false,
            syncToSalesforce: true,
            dryRun: true,
            hasHeader: true,
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        );

        $job->handle(
            app(\App\Services\ContactImport\ContactCsvRowMapper::class),
            app(\App\Services\ContactImport\ContactTextHomologationService::class),
            $tracker,
        );

        $this->assertDatabaseCount(ContactSubmission::class, 0);

        $snapshot = $tracker->snapshot($importId);

        $this->assertSame('completed', $snapshot['status']);
        $this->assertTrue($snapshot['dry_run']);
        $this->assertSame(1, $snapshot['processed']);
        $this->assertSame(1, $snapshot['created']);
        $this->assertSame(0, $snapshot['synced']);
        $this->assertSame(0, $snapshot['sync_failed']);
        $logs = implode("\n", $snapshot['logs']);

        $this->assertStringContainsString('Nombre: Jane Doe', $logs);
        $this->assertStringContainsString('Email: jane@example.com', $logs);
        $this->assertStringContainsString('Teléfono: +56911111111', $logs);
        $this->assertStringContainsString('Proyecto: Edificio Inn', $logs);
        $this->assertStringContainsString('Comuna: Puerto Varas', $logs);
        $this->assertStringContainsString('No se crearon contactos', $logs);
    }
}
