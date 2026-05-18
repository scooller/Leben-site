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
        $this->assertStringContainsString('No se crearon contactos', implode("\n", $snapshot['logs']));
    }
}
