<?php

namespace App\Console\Commands;

use App\Services\Salesforce\SalesforceService;
use Illuminate\Console\Command;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;

class TestSyncPlants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:sync-plants';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar sincronización de plantas con Salesforce';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Probando conexión con Salesforce...');

        try {
            // 1. Probar autenticación
            $this->info('');
            $this->info('📝 Paso 1: Autenticando con Salesforce...');
            Forrest::authenticate();
            $this->info('✅ Autenticación exitosa');

            // 2. Probar obtención de recursos
            $this->info('');
            $this->info('📝 Paso 2: Obteniendo recursos de Salesforce...');
            $resources = Forrest::resources();
            $this->info('✅ Recursos obtenidos: '.json_encode(array_keys($resources)));

            // 3. Probar consulta simple
            $this->info('');
            $this->info('📝 Paso 3: Probando consulta SOQL...');
            $result = Forrest::query('SELECT COUNT() FROM Product2');
            $this->info('✅ Total de productos en Salesforce: '.$result['totalSize']);

            // 4. Probar servicio de plantas
            $this->info('');
            $this->info('📝 Paso 4: Obteniendo plantas con filtros...');
            $service = app(SalesforceService::class);
            $plants = $service->findPlants(cacheTtl: 0); // Sin caché para probar
            $this->info('✅ Plantas obtenidas: '.count($plants));

            if (count($plants) > 0) {
                $this->info('');
                $this->info('📊 Primera planta de ejemplo:');
                $firstPlant = $plants[0];
                $this->table(
                    ['Campo', 'Valor'],
                    [
                        ['ID', $firstPlant['id']],
                        ['Nombre', $firstPlant['name']],
                        ['Código', $firstPlant['product_code']],
                        ['Programa', $firstPlant['programa']],
                        ['Precio Base', $firstPlant['precio_base']],
                        ['Precio Lista', $firstPlant['precio_lista']],
                        ['Proyecto ID', $firstPlant['proyecto_id'] ?? 'null'],
                    ]
                );
            }

            $this->info('');
            $this->info('✅ ¡Todas las pruebas pasaron correctamente!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('');
            $this->error('❌ Error: '.$e->getMessage());
            $this->error('');
            $this->error('Detalles técnicos:');
            $this->error('Tipo: '.get_class($e));
            $this->error('Archivo: '.$e->getFile().':'.$e->getLine());
            $this->error('');
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
