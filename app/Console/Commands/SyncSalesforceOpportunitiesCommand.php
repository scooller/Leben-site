<?php

namespace App\Console\Commands;

use App\Services\Salesforce\SalesforceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Throwable;

class SyncSalesforceOpportunitiesCommand extends Command
{
    protected $signature = 'salesforce:sync-opportunities {--since= : Fecha/hora base ISO8601 para SystemModstamp} {--limit=2000 : Maximo de registros a sincronizar (1-2000)}';

    protected $description = 'Sincroniza snapshots locales de oportunidades desde Salesforce y recalcula métricas de brokers.';

    public function handle(SalesforceService $salesforceService): int
    {
        $sinceOption = $this->option('since');
        $limit = (int) $this->option('limit');
        $limit = max(1, min($limit, 2000));

        $since = null;
        if (is_string($sinceOption) && trim($sinceOption) !== '') {
            try {
                $since = Carbon::parse($sinceOption)->utc();
            } catch (Throwable) {
                $this->error('Parametro --since invalido. Usa formato ISO8601, por ejemplo: 2026-05-01T00:00:00Z');

                return self::FAILURE;
            }
        }

        $this->info('Iniciando sincronizacion de oportunidades Salesforce...');

        try {
            Forrest::authenticate();
        } catch (Throwable $exception) {
            $this->warn('Advertencia de autenticacion Forrest: ' . $exception->getMessage());
        }

        $result = $salesforceService->syncOpportunitiesIncrementally($since, $limit);

        if (($result['success'] ?? false) !== true) {
            $this->error((string) ($result['message'] ?? 'Error durante sincronizacion de oportunidades.'));

            return self::FAILURE;
        }

        $this->info((string) ($result['message'] ?? 'Sincronizacion completada.'));
        $this->line('Synced: ' . (string) ($result['synced'] ?? 0));
        $this->line('Created: ' . (string) ($result['created'] ?? 0));
        $this->line('Updated: ' . (string) ($result['updated'] ?? 0));
        $this->line('Watermark: ' . (string) ($result['watermark'] ?? '-'));

        return self::SUCCESS;
    }
}
