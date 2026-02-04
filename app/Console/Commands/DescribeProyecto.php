<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;

class DescribeProyecto extends Command
{
    protected $signature = 'describe:proyecto';
    protected $description = 'Describe Proyecto__c object fields';

    public function handle()
    {
        try {
            $this->info('🔍 Obteniendo descripción de Proyecto__c...');
            
            // Usar la API describe de Forrest
            $describe = Forrest::describe('Proyecto__c');
            
            $this->info("✓ Descripción obtenida\n");
            
            // Imprimir todos los campos
            $fields = $describe['fields'] ?? [];
            
            $this->info("Total de campos: " . count($fields) . "\n");
            $this->info("=== CAMPOS DISPONIBLES ===\n");
            
            foreach ($fields as $field) {
                $name = $field['name'] ?? 'unknown';
                $label = $field['label'] ?? '';
                $type = $field['type'] ?? '';
                $updateable = $field['updateable'] ? '✓' : '✗';
                
                $this->line(sprintf(
                    "%-40s | Label: %-30s | Type: %-15s | Updateable: %s",
                    $name,
                    $label,
                    $type,
                    $updateable
                ));
            }
            
            // También guardar en un archivo para referencia
            $fieldNames = array_column($fields, 'name');
            file_put_contents(
                storage_path('proyecto_fields.txt'),
                implode("\n", $fieldNames)
            );
            
            $this->info("\n✅ Campos guardados en storage/proyecto_fields.txt");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }
}
