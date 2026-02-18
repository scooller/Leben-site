<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;

class TestProduct2Query extends Command
{
    protected $signature = 'test:product2-query';

    protected $description = 'Test Product2 SOQL query';

    public function handle()
    {
        try {
            $this->info('🔍 Probando consulta simple a Product2...');

            // Query simple sin filtros
            $result = Forrest::query('SELECT COUNT() FROM Product2');
            $this->info('✓ Total de Product2: '.($result['totalSize'] ?? 'unknown'));

            $this->info("\n📋 Consultando un registro de Product2...");
            $result = Forrest::query('SELECT Id, Name FROM Product2 LIMIT 1');

            if (! empty($result['records'])) {
                $record = $result['records'][0];
                $this->info('✓ Primer registro encontrado:');
                $this->line(json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->warn('⚠️ No hay registros de Product2');
            }

            // Ahora intentar con IsActive
            $this->info("\n📋 Consultando Product2 con IsActive = true...");
            $result = Forrest::query('SELECT COUNT() FROM Product2 WHERE IsActive = true');
            $this->info('✓ Total activos: '.($result['totalSize'] ?? 'unknown'));

            // Ahora con todos los campos
            $this->info("\n📋 Consultando con todos los campos...");
            $soql = 'SELECT Id, Name, ProductCode, Orientacion2__c, Programa__c, Programa2__c, Piso__c, '
                .'Precio_Base__c, Precio_Lista__c, '
                .'Superficie_Total_Producto_Principal__c, Superficie_Interior__c, Superficie_Util__c, '
                .'Opportunity__c, Superficie_Terraza__c, Superficie_Vendible__c '
                .'FROM Product2 WHERE IsActive = true LIMIT 1';

            $result = Forrest::query($soql);
            $this->info('✓ Consulta con todos los campos exitosa');

            if (! empty($result['records'])) {
                $record = $result['records'][0];
                $this->line(json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            // Ahora con los filtros completos
            $this->info("\n📋 Consultando con filtros Estado__c = 'Disponible' Y Tipo_Producto__c = 'DEPARTAMENTO'...");
            $soql = "SELECT COUNT() FROM Product2 WHERE IsActive = true AND Estado__c = 'Disponible' AND Tipo_Producto__c = 'DEPARTAMENTO'";

            $result = Forrest::query($soql);
            $this->info('✓ Total con filtros: '.($result['totalSize'] ?? 'unknown'));

            // Y finalmente el query completo
            $this->info("\n📋 Consultando registros completos con filtros...");
            $soql = 'SELECT Id, Name, ProductCode, Orientacion2__c, Programa__c, Programa2__c, Piso__c, '
                .'Precio_Base__c, Precio_Lista__c, '
                .'Superficie_Total_Producto_Principal__c, Superficie_Interior__c, Superficie_Util__c, '
                .'Opportunity__c, Superficie_Terraza__c, Superficie_Vendible__c '
                ."FROM Product2 WHERE IsActive = true AND Estado__c = 'Disponible' AND Tipo_Producto__c = 'DEPARTAMENTO' LIMIT 2";

            $result = Forrest::query($soql);
            $this->info('✓ Registros encontrados: '.count($result['records'] ?? []));

            if (! empty($result['records'])) {
                foreach ($result['records'] as $idx => $record) {
                    $this->line("\n=== Registro ".($idx + 1).' ===');
                    $this->line(json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            } else {
                $this->warn('⚠️ No hay registros con esos filtros');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());
            $this->error('Tipo: '.get_class($e));

            return Command::FAILURE;
        }
    }
}
