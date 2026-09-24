<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ProductionSyncProgress;
use App\Models\User;
use App\Services\ProductionSync\ProductionSyncProgressTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductionSyncProgressTimeoutTest extends TestCase
{


    public function test_progress_page_marks_sync_failed_and_logs_timeout_when_not_started_and_exceeded(): void
    {
        $syncId = 'test-timeout-sync';
        $tracker = app(ProductionSyncProgressTracker::class);

        // Initialize sync with 0 steps (queued but not yet started)
        $tracker->initialize($syncId, 0, 'https://admin.ileben.cl');

        // Backdate started_at in cache (5 minutes ago, exceeding default 120s timeout)
        $metaKey = "production_sync:{$syncId}:meta";
        $meta = (array) Cache::get($metaKey, []);
        $meta['started_at'] = Carbon::now()->subMinutes(5)->toDateTimeString();
        Cache::put($metaKey, $meta, 3600);

        $page = new ProductionSyncProgress();
        $page->syncId = $syncId;
        $page->refreshProgress();

        $this->assertSame('failed', $page->snapshot['status']);
        $this->assertStringContainsString('timeout', strtolower((string) $page->snapshot['error']));

        // Check logs contain timeout fatal error
        $logs = (array) ($page->snapshot['logs'] ?? []);
        $this->assertNotEmpty($logs);
        $hasTimeoutLog = false;
        foreach ($logs as $log) {
            if (str_contains($log, 'Tiempo de espera agotado') && str_contains($log, 'timeout')) {
                $hasTimeoutLog = true;
                break;
            }
        }
        $this->assertTrue($hasTimeoutLog, 'El log en vivo debe contener el mensaje de error por timeout.');
    }

    public function test_progress_page_does_not_timeout_when_still_within_limit(): void
    {
        $syncId = 'test-active-sync';
        $tracker = app(ProductionSyncProgressTracker::class);

        // Initialize sync that started 5 seconds ago
        $tracker->initialize($syncId, 0, 'https://admin.ileben.cl');

        $page = new ProductionSyncProgress();
        $page->syncId = $syncId;
        $page->refreshProgress();

        $this->assertSame('running', $page->snapshot['status']);
        $this->assertNull($page->snapshot['error']);
    }

    public function test_progress_page_does_not_timeout_if_import_has_already_started(): void
    {
        $syncId = 'test-started-sync';
        $tracker = app(ProductionSyncProgressTracker::class);

        // Initialize sync and simulate that import has started with total steps and processed records
        $tracker->initialize($syncId, 500, 'https://admin.ileben.cl');
        $tracker->increment($syncId, 'processed', 150);

        // Backdate started_at by 10 minutes
        $metaKey = "production_sync:{$syncId}:meta";
        $meta = (array) Cache::get($metaKey, []);
        $meta['started_at'] = Carbon::now()->subMinutes(10)->toDateTimeString();
        Cache::put($metaKey, $meta, 3600);

        $page = new ProductionSyncProgress();
        $page->syncId = $syncId;
        $page->refreshProgress();

        // Must still be running, NOT failed by timeout
        $this->assertSame('running', $page->snapshot['status']);
        $this->assertNull($page->snapshot['error']);
    }
}
