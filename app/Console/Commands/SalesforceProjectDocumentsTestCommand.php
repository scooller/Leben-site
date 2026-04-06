<?php

namespace App\Console\Commands;

use App\Services\Salesforce\SalesforceService;
use Illuminate\Console\Command;

class SalesforceProjectDocumentsTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salesforce:test-project-documents {--refresh-cache : Fuerza consulta sin usar caché} {--name=* : Nombres de documentos específicos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba recuperación de logo y portada (Document público) desde Salesforce';

    /**
     * Execute the console command.
     */
    public function handle(SalesforceService $salesforceService): int
    {
        $names = $this->option('name');

        if (! is_array($names) || $names === []) {
            $names = $salesforceService->getDefaultProjectDocumentNames();
        }

        $this->info('Consultando documentos públicos en Salesforce...');

        try {
            $documents = $salesforceService->findPublicProjectDocuments(
                $names,
                $this->option('refresh-cache') ? 0 : null,
            );

            if ($documents === []) {
                $this->warn('No se encontraron documentos para los nombres solicitados.');

                return self::SUCCESS;
            }

            $rows = array_map(static fn (array $document): array => [
                $document['project_name'] ?? 'N/A',
                $document['asset_kind'] ?? 'N/A',
                $document['name'] ?? 'N/A',
                $document['id'] ?? 'N/A',
                $document['type'] ?? 'N/A',
                (string) ($document['body_length'] ?? 0),
                $document['download_url'] ?? '(configurar SF_PUBLIC_SITE_URL + SF_ORG_ID o SF_INSTANCE_URL)',
            ], $documents);

            $this->table(
                ['Proyecto', 'Asset', 'Documento', 'Id', 'Tipo', 'BodyLength', 'Download URL'],
                $rows
            );

            $this->info('Documentos recuperados: '.count($documents));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Error consultando documentos en Salesforce: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
