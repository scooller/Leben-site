<?php

namespace App\Services\ContactImport;

use Illuminate\Support\Facades\Cache;

class ContactImportProgressTracker
{
    private const TTL_SECONDS = 21600;

    public function initialize(string $importId, int $totalRows, string $channelName, bool $syncToSalesforce): void
    {
        Cache::put($this->metaKey($importId), [
            'import_id' => $importId,
            'status' => 'running',
            'total_rows' => max(0, $totalRows),
            'channel_name' => $channelName,
            'sync_to_salesforce' => $syncToSalesforce,
            'started_at' => now()->toDateTimeString(),
            'finished_at' => null,
            'error' => null,
        ], self::TTL_SECONDS);

        foreach (['processed', 'created', 'failed', 'warnings', 'synced', 'sync_failed'] as $counter) {
            Cache::put($this->counterKey($importId, $counter), 0, self::TTL_SECONDS);
        }

        Cache::put($this->logsKey($importId), [], self::TTL_SECONDS);
    }

    public function increment(string $importId, string $counter, int $by = 1): void
    {
        $key = $this->counterKey($importId, $counter);

        if (! Cache::has($key)) {
            Cache::put($key, 0, self::TTL_SECONDS);
        }

        Cache::increment($key, $by);
        Cache::put($key, (int) Cache::get($key, 0), self::TTL_SECONDS);
    }

    public function addLog(string $importId, string $message): void
    {
        $logs = (array) Cache::get($this->logsKey($importId), []);
        $logs[] = '[' . now()->format('H:i:s') . '] ' . $message;

        if (count($logs) > 200) {
            $logs = array_slice($logs, -200);
        }

        Cache::put($this->logsKey($importId), $logs, self::TTL_SECONDS);
    }

    public function markCompleted(string $importId): void
    {
        $meta = (array) Cache::get($this->metaKey($importId), []);
        $meta['status'] = 'completed';
        $meta['finished_at'] = now()->toDateTimeString();

        Cache::put($this->metaKey($importId), $meta, self::TTL_SECONDS);
    }

    public function markFailed(string $importId, string $error): void
    {
        $meta = (array) Cache::get($this->metaKey($importId), []);
        $meta['status'] = 'failed';
        $meta['error'] = $error;
        $meta['finished_at'] = now()->toDateTimeString();

        Cache::put($this->metaKey($importId), $meta, self::TTL_SECONDS);
        $this->addLog($importId, 'Error fatal: ' . $error);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(string $importId): array
    {
        $meta = (array) Cache::get($this->metaKey($importId), []);

        if ($meta === []) {
            return [
                'status' => 'not_found',
                'total_rows' => 0,
                'processed' => 0,
                'created' => 0,
                'failed' => 0,
                'warnings' => 0,
                'synced' => 0,
                'sync_failed' => 0,
                'progress_percent' => 0,
                'logs' => [],
                'channel_name' => '-',
                'sync_to_salesforce' => false,
                'started_at' => null,
                'finished_at' => null,
                'error' => null,
            ];
        }

        $totalRows = (int) ($meta['total_rows'] ?? 0);
        $processed = (int) Cache::get($this->counterKey($importId, 'processed'), 0);
        $progressPercent = $totalRows > 0
            ? min(100, (int) floor(($processed / $totalRows) * 100))
            : 0;

        return [
            'status' => (string) ($meta['status'] ?? 'running'),
            'total_rows' => $totalRows,
            'processed' => $processed,
            'created' => (int) Cache::get($this->counterKey($importId, 'created'), 0),
            'failed' => (int) Cache::get($this->counterKey($importId, 'failed'), 0),
            'warnings' => (int) Cache::get($this->counterKey($importId, 'warnings'), 0),
            'synced' => (int) Cache::get($this->counterKey($importId, 'synced'), 0),
            'sync_failed' => (int) Cache::get($this->counterKey($importId, 'sync_failed'), 0),
            'progress_percent' => $progressPercent,
            'logs' => (array) Cache::get($this->logsKey($importId), []),
            'channel_name' => (string) ($meta['channel_name'] ?? '-'),
            'sync_to_salesforce' => (bool) ($meta['sync_to_salesforce'] ?? false),
            'started_at' => $meta['started_at'] ?? null,
            'finished_at' => $meta['finished_at'] ?? null,
            'error' => $meta['error'] ?? null,
        ];
    }

    private function metaKey(string $importId): string
    {
        return "contact_import:{$importId}:meta";
    }

    private function counterKey(string $importId, string $counter): string
    {
        return "contact_import:{$importId}:counter:{$counter}";
    }

    private function logsKey(string $importId): string
    {
        return "contact_import:{$importId}:logs";
    }
}
