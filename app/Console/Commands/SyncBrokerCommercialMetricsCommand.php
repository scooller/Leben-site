<?php

namespace App\Console\Commands;

use App\Services\Salesforce\SalesforceService;
use Illuminate\Console\Command;

class SyncBrokerCommercialMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salesforce:sync-broker-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula métricas comerciales de brokers desde snapshots locales de oportunidades.';

    /**
     * Execute the console command.
     */
    public function handle(SalesforceService $salesforceService): int
    {
        $this->info('Recalculando métricas comerciales de brokers...');

        $salesforceService->syncBrokerCommercialMetricsFromSnapshots();

        $this->info('Métricas comerciales de brokers actualizadas correctamente.');

        return self::SUCCESS;
    }
}
