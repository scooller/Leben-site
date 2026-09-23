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
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_progress_page_marks_sync_failed_and_logs_timeout_when_exceeded(): void
    {
        $syncId = 'test-timeout-sync';
        $tracker = app(ProductionSyncProgressTracker::class);

        // Initialize sync that started 5 minutes ago (exceeding default 120s timeout)
        $tracker->initialize($syncId, 10, 'https://admin.ileben.cl');

        // Backdate started_at in cache
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
        $tracker->initialize($syncId, 10, 'https://admin.ileben.cl');

        $page = new ProductionSyncProgress();
        $page->syncId = $syncId;
        $page->refreshProgress();

        $this->assertSame('running', $page->snapshot['status']);
        $this->assertNull($page->snapshot['error']);
    }
}
