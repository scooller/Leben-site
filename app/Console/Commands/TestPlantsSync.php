<?php

namespace App\Console\Commands;

use App\Services\Salesforce\SalesforceService;
use Illuminate\Console\Command;

class TestPlantsSync extends Command
{
    protected $signature = 'test:plants-sync';
    protected $description = 'Test plantas sync from Salesforce';

    public function handle()
    {
        $this->info('🔄 Iniciando test de sincronización de plantas...');
        
        try {
            $service = app(SalesforceService::class);
            
            $this->info('Obteniendo plantas desde Salesforce...');
            $plants = $service->findPlants(0); // 0 segundos de cache para forzar refresh
            
            $this->info("✅ Plantas encontradas: " . count($plants));
            
            if (!empty($plants)) {
                $this->line("\n📋 Primera planta:");
                $this->line(json_encode($plants[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->warn("⚠️ No se encontraron plantas en Salesforce");
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}
