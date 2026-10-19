<?php

namespace App\Console\Commands;

use App\Models\Plant;
use Illuminate\Console\Command;

class GeneratePlantSkusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plants:generate-sku
                            {--actualizar : Actualizar y reemplazar SKUs ya generados en la base de datos}
                            {--update : Alias de --actualizar para reemplazar SKUs ya existentes}
                            {--force : Forzar la sobreescritura de SKUs existentes}
                            {--dry-run : Simular la generación sin guardar cambios en la base de datos}
                            {--project= : Filtrar plantas por slug o ID del proyecto}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera y asigna SKUs canónicos normalizados para las plantas que no tengan uno asignado o los actualiza si se solicita';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $shouldUpdateExisting = (bool) ($this->option('actualizar') || $this->option('update') || $this->option('force'));
        $dryRun = (bool) $this->option('dry-run');
        $projectFilter = $this->option('project');

        $query = Plant::query()->with('proyecto');

        if (! $shouldUpdateExisting) {
            $query->where(function ($q) {
                $q->whereNull('sku')->orWhere('sku', '');
            });
        }

        if (filled($projectFilter)) {
            $query->whereHas('proyecto', function ($q) use ($projectFilter) {
                $q->where('slug', $projectFilter)
                    ->orWhere('id', $projectFilter);
            });
        }

        $total = $query->count();
        if ($total === 0) {
            $this->info('No se encontraron plantas que requieran generación de SKU.');

            return self::SUCCESS;
        }

        $this->info("Procesando {$total} plantas".($dryRun ? ' (MODO SIMULACIÓN - DRY RUN)' : '').'...');

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        $updated = 0;

        $query->chunkById(100, function ($plants) use ($shouldUpdateExisting, $dryRun, &$processed, &$updated, $bar) {
            foreach ($plants as $plant) {
                $processed++;
                $newSku = $plant->resolveSku($shouldUpdateExisting);

                if ($plant->sku !== $newSku) {
                    $updated++;
                    if (! $dryRun) {
                        $plant->sku = $newSku;
                        $plant->saveQuietly();
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total evaluadas', $processed],
                ['SKUs generados/actualizados', $updated],
                ['Modo', $dryRun ? 'Dry Run (sin cambios en DB)' : 'Persistido en DB'],
            ]
        );

        return self::SUCCESS;
    }
}
