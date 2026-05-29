<?php

namespace App\Services\ProductionSync;

use Illuminate\Support\Facades\Cache;

class ProductionSyncProgressTracker
{
    private const TTL_SECONDS = 21600;

    public function initialize(string $syncId, int $totalSteps, string $baseUrl): void
    {
        Cache::put($this->metaKey($syncId), [
            'sync_id' => $syncId,
            'status' => 'running',
            'total_steps' => max(0, $totalSteps),
            'base_url' => $baseUrl,
            'started_at' => now()->toDateTimeString(),
            'finished_at' => null,
            'error' => null,
        ], self::TTL_SECONDS);

        foreach (['processed', 'created', 'updated', 'skipped', 'failed'] as $counter) {
            Cache::put($this->counterKey($syncId, $counter), 0, self::TTL_SECONDS);
        }

        Cache::put($this->logsKey($syncId), [], self::TTL_SECONDS);
    }

    public function setTotalSteps(string $syncId, int $totalSteps): void
    {
        $meta = (array) Cache::get($this->metaKey($syncId), []);
        $meta['total_steps'] = max(0, $totalSteps);

        Cache::put($this->metaKey($syncId), $meta, self::TTL_SECONDS);
    }

    public function increment(string $syncId, string $counter, int $by = 1): void
    {
        $key = $this->counterKey($syncId, $counter);

        if (! Cache::has($key)) {
            Cache::put($key, 0, self::TTL_SECONDS);
        }

        Cache::increment($key, $by);
        Cache::put($key, (int) Cache::get($key, 0), self::TTL_SECONDS);
    }

    public function addLog(string $syncId, string $message): void
    {
        $logs = (array) Cache::get($this->logsKey($syncId), []);
        $logs[] = '[' . now()->format('H:i:s') . '] ' . $message;

        if (count($logs) > 200) {
            $logs = array_slice($logs, -200);
        }

        Cache::put($this->logsKey($syncId), $logs, self::TTL_SECONDS);
    }

    public function markCompleted(string $syncId): void
    {
        $meta = (array) Cache::get($this->metaKey($syncId), []);
        $meta['status'] = 'completed';
        $meta['finished_at'] = now()->toDateTimeString();

        Cache::put($this->metaKey($syncId), $meta, self::TTL_SECONDS);
    }

    public function markFailed(string $syncId, string $error): void
    {
        $meta = (array) Cache::get($this->metaKey($syncId), []);
        $meta['status'] = 'failed';
        $meta['error'] = $error;
        $meta['finished_at'] = now()->toDateTimeString();

        Cache::put($this->metaKey($syncId), $meta, self::TTL_SECONDS);
        $this->addLog($syncId, 'Error fatal: ' . $error);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(string $syncId): array
    {
        $meta = (array) Cache::get($this->metaKey($syncId), []);

        if ($meta === []) {
            return [
                'status' => 'not_found',
                'total_steps' => 0,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'progress_percent' => 0,
                'logs' => [],
                'base_url' => '-',
                'started_at' => null,
                'finished_at' => null,
                'error' => null,
            ];
        }

        $totalSteps = (int) ($meta['total_steps'] ?? 0);
        $processed = (int) Cache::get($this->counterKey($syncId, 'processed'), 0);
        $progressPercent = $totalSteps > 0
            ? min(100, (int) floor(($processed / $totalSteps) * 100))
            : 0;

        return [
            'status' => (string) ($meta['status'] ?? 'running'),
            'total_steps' => $totalSteps,
            'processed' => $processed,
            'created' => (int) Cache::get($this->counterKey($syncId, 'created'), 0),
            'updated' => (int) Cache::get($this->counterKey($syncId, 'updated'), 0),
            'skipped' => (int) Cache::get($this->counterKey($syncId, 'skipped'), 0),
            'failed' => (int) Cache::get($this->counterKey($syncId, 'failed'), 0),
            'progress_percent' => $progressPercent,
            'logs' => (array) Cache::get($this->logsKey($syncId), []),
            'base_url' => (string) ($meta['base_url'] ?? '-'),
            'started_at' => $meta['started_at'] ?? null,
            'finished_at' => $meta['finished_at'] ?? null,
            'error' => $meta['error'] ?? null,
        ];
    }

    private function metaKey(string $syncId): string
    {
        return "production_sync:{$syncId}:meta";
    }

    private function counterKey(string $syncId, string $counter): string
    {
        return "production_sync:{$syncId}:counter:{$counter}";
    }

    private function logsKey(string $syncId): string
    {
        return "production_sync:{$syncId}:logs";
    }
}
