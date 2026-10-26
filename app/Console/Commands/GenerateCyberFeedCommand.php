<?php

namespace App\Console\Commands;

use App\Services\CyberFeed\CyberFeedService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateCyberFeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cyber:generate-feed
                            {--output= : Ruta de destino personalizada (por defecto storage/app/public/cyber/feed.json)}
                            {--pretty : Formatear JSON con sangría legible}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera el Cyber Feed canónico estático en formato JSON para distribución o respaldo CDN';

    /**
     * Execute the console command.
     */
    public function handle(CyberFeedService $feedService): int
    {
        $startTime = microtime(true);
        $this->info('Iniciando generación de Cyber Feed...');

        $outputPath = $this->option('output')
            ?: storage_path('app/public/cyber/feed.json');

        // Construir feed completo para la exportación estática (página 1, hasta 250 o todos)
        $feed = $feedService->buildFeed([
            'is_cyber' => true,
            'per_page' => 250,
            'page' => 1,
        ]);

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $jsonContent = json_encode($feed, $flags);

        $directory = dirname($outputPath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($outputPath, $jsonContent);

        // Si no se especificó un archivo destino exclusivo, también exportar el feed canónico general
        if (! $this->option('output')) {
            $genericOutputPath = storage_path('app/public/feed.json');
            $genericFeed = $feedService->buildFeed([
                'is_cyber' => false,
                'per_page' => 250,
                'page' => 1,
            ]);
            File::put($genericOutputPath, json_encode($genericFeed, $flags));
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $fileSizeKb = round(strlen($jsonContent) / 1024, 2);
        $totalOffers = count($feed['offers']);
        $exclusions = $feed['exclusions_count'];

        $this->info("Cyber Feed generado exitosamente en: {$outputPath}");
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Ofertas publicadas', $totalOffers],
                ['Ofertas excluidas', $exclusions],
                ['Tamaño archivo', "{$fileSizeKb} KB"],
                ['Tiempo de ejecución', "{$duration} ms"],
            ]
        );

        return self::SUCCESS;
    }
}
