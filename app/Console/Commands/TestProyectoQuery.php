<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;

class TestProyectoQuery extends Command
{
    protected $signature = 'test:proyecto-query';
    protected $description = 'Test Proyecto__c SOQL query';

    public function handle()
    {
        try {
            $this->info('� Refrescando autenticación...');
            Forrest::authenticate();
            $this->info('✓ Autenticación refrescada\n');
            
            $this->info('�🔍 Probando consulta simple a Proyecto__c...');
            
            // Query simple sin filtros
            $result = Forrest::query("SELECT COUNT() FROM Proyecto__c");
            $this->info("✓ Total de Proyecto__c: " . ($result['totalSize'] ?? 'unknown'));
            
            $this->info("\n📋 Consultando un registro de Proyecto__c...");
            $result = Forrest::query("SELECT Id, Name FROM Proyecto__c LIMIT 1");
            
            if (!empty($result['records'])) {
                $record = $result['records'][0];
                $this->info("✓ Primer registro encontrado:");
                $this->line(json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->warn("⚠️ No hay registros de Proyecto__c");
            }
            
            // Ahora intentar con IsDeleted
            $this->info("\n📋 Consultando Proyecto__c con IsDeleted = false...");
            $result = Forrest::query("SELECT COUNT() FROM Proyecto__c WHERE IsDeleted = false");
            $this->info("✓ Total no eliminados: " . ($result['totalSize'] ?? 'unknown'));
            
            // Ahora con Activo__c
            $this->info("\n📋 Consultando Proyecto__c con Activo__c = true...");
            $result = Forrest::query("SELECT COUNT() FROM Proyecto__c WHERE IsDeleted = false AND Activo__c = true");
            $this->info("✓ Total activos: " . ($result['totalSize'] ?? 'unknown'));
            
            // Query con campos principales
            $this->info("\n📋 Consultando con campos principales...");
            $soql = "SELECT Id, Name, Descripci_n__c, Direccion__c, Comuna__c, Provincia__c, Region__c "
                . "FROM Proyecto__c WHERE IsDeleted = false LIMIT 1";
            
            $result = Forrest::query($soql);
            $this->info("✓ Consulta con campos principales exitosa");
            
            if (!empty($result['records'])) {
                $record = $result['records'][0];
                $this->line(json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            
            // Ahora con el filtro Tipo_Producto__c
            $this->info("\n📋 Consultando con filtro Tipo_Producto__c = 'DEPARTAMENTO'...");
            $soql = "SELECT COUNT() FROM Proyecto__c WHERE IsDeleted = false AND Activo__c = true AND Tipo_Producto__c = 'DEPARTAMENTO'";
            
            $result = Forrest::query($soql);
            $this->info("✓ Total con filtros: " . ($result['totalSize'] ?? 'unknown'));
            
            // Y finalmente el query completo
            $this->info("\n📋 Consultando registros completos con filtros...");
            $soql = "SELECT Id, Name, Descripci_n__c, Direccion__c, Comuna__c, Provincia__c, Region__c, "
                . "Email__c, Telefono__c, Pagina_Web_Proyecto__c, Razon_Social__c, RUT__c, "
                . "Fecha_Inicio_Ventas__c, Fecha_Recepcion_Municipal__c, Etapa__c, Horario_Atencion__c, "
                . "Dscto_M_x_Prod_Principal_Porc__c, Dscto_M_x_Prod_Principal_UF__c, "
                . "Dscto_M_x_Bodega_Porc__c, Dscto_M_x_Bodega_UF__c, "
                . "Dscto_M_x_Estac_Porc__c, Dscto_M_x_Estac_UF__c, "
                . "Dscto_Max_Otros_Porc__c, Dscto_Max_Otros_Prod_UF__c, "
                . "Dscto_Maximo_Aporte_Leben__c, "
                . "N_A_os_1__c, N_A_os_2__c, N_A_os_3__c, N_A_os_4__c, "
                . "Valor_Reserva_Exigido_Defecto_Peso__c, Valor_Reserva_Exigido_Min_Peso__c, "
                . "Tasa__c, Entrega_Inmediata__c "
                . "Dscto_Maximo_Aporte_Leven__c, N_Anos_1_4__c, "
                . "Valor_Reserva_Exigido_Defecto_Peso__c, Valor_Reserva_Exigido_Min_Peso__c, "
                . "Tasa__c, Entrega_Inmediata__c "
                . "FROM Proyecto__c WHERE IsDeleted = false AND Activo__c = true AND Tipo_Producto__c = 'DEPARTAMENTO' LIMIT 2";
            
            $result = Forrest::query($soql);
            $this->info("✓ Registros encontrados: " . count($result['records'] ?? []));
            
            if (!empty($result['records'])) {
                foreach ($result['records'] as $idx => $record) {
                    $this->line("\n=== Registro " . ($idx + 1) . " ===");
                    $this->line(json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            } else {
                $this->warn("⚠️ No hay registros con esos filtros");
            }
            
            $this->info("\n✅ Prueba completada exitosamente");
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }
}
