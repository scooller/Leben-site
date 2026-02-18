<?php

namespace App\Console\Commands;

use App\Services\Salesforce\SalesforceService;
use Illuminate\Console\Command;

class TestProjectsQuery extends Command
{
    protected $signature = 'test:proyecto-query';

    protected $description = 'Prueba la consulta de proyectos desde Salesforce';

    public function handle(): int
    {
        try {
            $this->info('🔍 Buscando proyectos en Salesforce...');

            $salesforceService = app(SalesforceService::class);
            $result = $salesforceService->findProjects();

            if (is_array($result) && count($result) > 0) {
                $this->info('✓ Registros encontrados: '.count($result));

                foreach ($result as $idx => $record) {
                    $this->line("\n=== Proyecto ".($idx + 1).' ===');
                    $this->line('Nombre: '.($record['name'] ?? 'N/A'));
                    $this->line('Código SF: '.($record['id'] ?? 'N/A'));
                    $this->line('Etapa: '.($record['etapa'] ?? 'N/A'));
                    $this->line('Región: '.($record['region'] ?? 'N/A'));
                    $this->line('Dscto Principal: '.($record['dscto_m_x_prod_principal_porc'] ?? 'N/A').'%');
                }
            } else {
                $this->warn('⚠️ No se encontraron proyectos');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());
            $this->error('Tipo: '.get_class($e));

            return Command::FAILURE;
        }
    }
}
