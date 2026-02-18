<?php

namespace App\Services\Salesforce;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Omniphx\Forrest\Exceptions\MissingKeyException;
use Omniphx\Forrest\Exceptions\MissingResourceException;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;

class SalesforceService
{
    /**
     * Tiempo de caché predeterminado para consultas SOQL (en segundos)
     */
    protected int $defaultCacheTtl = 900; // 15 minutos

    /**
     * Ejecutar una consulta SOQL con caché automático
     */
    public function query(string $soql, ?int $cacheTtl = null): array
    {
        $cacheKey = $this->generateCacheKey($soql);
        $ttl = $cacheTtl ?? $this->defaultCacheTtl;

        return Cache::remember($cacheKey, $ttl, function () use ($soql) {
            try {
                $result = Forrest::query($soql);

                return $result['records'] ?? [];
            } catch (MissingKeyException|MissingResourceException $e) {
                // Re-autenticar si el token expiró o no hay recursos disponibles
                Log::info('Salesforce: Re-autenticando debido a: '.$e->getMessage());
                $this->authenticate();
                $result = Forrest::query($soql);

                return $result['records'] ?? [];
            }
        });
    }

    /**
     * Autenticar con Salesforce (útil para forzar refresh)
     */
    public function authenticate(): void
    {
        Log::info('Salesforce: Iniciando autenticación...');
        try {
            Forrest::authenticate();
            Log::info('Salesforce: Autenticación exitosa');
        } catch (\Exception $e) {
            Log::error('Salesforce: Error en autenticación - '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Generar clave de caché basada en la consulta SOQL
     */
    protected function generateCacheKey(string $soql): string
    {
        return 'salesforce:soql:'.md5($soql);
    }

    /**
     * Limpiar todo el caché de Salesforce
     */
    public function clearCache(): void
    {
        Cache::flush(); // En producción, usar tags para ser más específico
    }

    /**
     * Obtener plantas desde Product2 disponibles
     * Estructura: Departamentos activos con estado "Disponible"
     *
     * @return array Array de plantas con estructura:
     *               [
     *               'id' => string,
     *               'name' => string,
     *               'product_code' => string,
     *               'orientacion' => string,
     *               'programa' => string,
     *               'programa2' => string,
     *               'piso' => string,
     *               'precio_base' => float,
     *               'precio_lista' => float,
     *               'superficie_total_principal' => float,
     *               'superficie_interior' => float,
     *               'superficie_util' => float,
     *               'opportunity_id' => string|null,
     *               'superficie_terraza' => float,
     *               'superficie_vendible' => float
     *               ]
     */
    public function findPlants(?int $cacheTtl = null): array
    {
        // SOQL para obtener plantas desde Product2
        $soql = 'SELECT Id, Name, ProductCode, Orientacion2__c, Programa__c, Programa2__c, Piso__c, '
            .'Precio_Base__c, Precio_Lista__c, '
            .'Superficie_Total_Producto_Principal__c, Superficie_Interior__c, Superficie_Util__c, '
            .'Opportunity__c, Superficie_Terraza__c, Superficie_Vendible__c, Proyecto__c '
            .'FROM Product2 '
            ."WHERE IsActive = true AND Estado__c = 'Disponible' AND Tipo_Producto__c = 'DEPARTAMENTO' "
            .'ORDER BY Name '
            .'LIMIT 1000';

        $ttl = $cacheTtl ?? $this->defaultCacheTtl;

        return Cache::remember('salesforce:plants', $ttl, function () use ($soql) {
            try {
                $result = Forrest::query($soql);
                $entries = $result['records'] ?? [];

                // Transformar estructura Salesforce a formato más amigable
                return array_map(function ($entry) {
                    return [
                        'id' => $entry['Id'] ?? null,
                        'name' => $entry['Name'] ?? null,
                        'product_code' => $entry['ProductCode'] ?? null,
                        'orientacion' => $entry['Orientacion2__c'] ?? null,
                        'programa' => $entry['Programa__c'] ?? null,
                        'programa2' => $entry['Programa2__c'] ?? null,
                        'piso' => $entry['Piso__c'] ?? null,
                        'precio_base' => (float) ($entry['Precio_Base__c'] ?? 0) ?: 0,
                        'precio_lista' => (float) ($entry['Precio_Lista__c'] ?? 0) ?: 0,
                        'superficie_total_principal' => (float) ($entry['Superficie_Total_Producto_Principal__c'] ?? 0),
                        'superficie_interior' => (float) ($entry['Superficie_Interior__c'] ?? 0),
                        'superficie_util' => (float) ($entry['Superficie_Util__c'] ?? 0),
                        'opportunity_id' => $entry['Opportunity__c'] ?? null,
                        'superficie_terraza' => (float) ($entry['Superficie_Terraza__c'] ?? 0),
                        'superficie_vendible' => (float) ($entry['Superficie_Vendible__c'] ?? 0),
                        'proyecto_id' => $entry['Proyecto__c'] ?? null,
                    ];
                }, $entries);
            } catch (MissingKeyException|MissingResourceException $e) {
                // Re-autenticar si el token expiró o no hay recursos disponibles
                Log::info('Salesforce: Re-autenticando plantas debido a: '.$e->getMessage());
                $this->authenticate();
                $result = Forrest::query($soql);
                $entries = $result['records'] ?? [];

                return array_map(function ($entry) {
                    return [
                        'id' => $entry['Id'] ?? null,
                        'name' => $entry['Name'] ?? null,
                        'product_code' => $entry['ProductCode'] ?? null,
                        'orientacion' => $entry['Orientacion2__c'] ?? null,
                        'programa' => $entry['Programa__c'] ?? null,
                        'programa2' => $entry['Programa2__c'] ?? null,
                        'piso' => $entry['Piso__c'] ?? null,
                        'precio_base' => (float) ($entry['Precio_Base__c'] ?? 0) ?: 0,
                        'precio_lista' => (float) ($entry['Precio_Lista__c'] ?? 0) ?: 0,
                        'superficie_total_principal' => (float) ($entry['Superficie_Total_Producto_Principal__c'] ?? 0),
                        'superficie_interior' => (float) ($entry['Superficie_Interior__c'] ?? 0),
                        'superficie_util' => (float) ($entry['Superficie_Util__c'] ?? 0),
                        'opportunity_id' => $entry['Opportunity__c'] ?? null,
                        'superficie_terraza' => (float) ($entry['Superficie_Terraza__c'] ?? 0),
                        'superficie_vendible' => (float) ($entry['Superficie_Vendible__c'] ?? 0),
                        'proyecto_id' => $entry['Proyecto__c'] ?? null,
                    ];
                }, $entries);
            }
        });
    }

    /**
     * Invalidar caché de plantas
     */
    public function invalidatePlantsCache(): void
    {
        Cache::forget('salesforce:plants');
        // Limpiar también el caché de plantas por pricebook (usar tags sería ideal aquí)
    }

    /**
     * Obtener proyectos desde Proyecto__c disponibles
     * Estructura: Proyectos activos de tipo DEPARTAMENTO
     *
     * @return array Array de proyectos con estructura:
     *               [
     *               'id' => string,
     *               'name' => string,
     *               'descripcion' => string|null,
     *               'direccion' => string|null,
     *               'comuna' => string|null,
     *               'provincia' => string|null,
     *               'region' => string|null,
     *               'email' => string|null,
     *               'telefono' => string|null,
     *               'pagina_web' => string|null,
     *               'razon_social' => string|null,
     *               'rut' => string|null,
     *               'fecha_inicio_ventas' => string|null,
     *               'fecha_entrega' => string|null,
     *               'etapa' => string|null,
     *               'horario_atencion' => string|null,
     *               'dscto_m_x_prod_principal_porc' => float,
     *               'dscto_m_x_prod_principal_uf' => float,
     *               'dscto_m_x_bodega_porc' => float,
     *               'dscto_m_x_bodega_uf' => float,
     *               'dscto_m_x_estac_porc' => float,
     *               'dscto_m_x_estac_uf' => float,
     *               'dscto_max_otros_porc' => float,
     *               'dscto_max_otros_prod_uf' => float,
     *               'dscto_maximo_aporte_leben' => float,
     *               'n_anos_1' => int|null,
     *               'n_anos_2' => int|null,
     *               'n_anos_3' => int|null,
     *               'n_anos_4' => int|null,
     *               'valor_reserva_exigido_defecto_peso' => float|null,
     *               'valor_reserva_exigido_min_peso' => float|null,
     *               'tasa' => float|null,
     *               'entrega_inmediata' => bool
     *               ]
     */
    public function findProjects(?int $cacheTtl = null): array
    {
        // Asegurar que Forrest esté autenticado
        try {
            Forrest::authenticate();
        } catch (\Exception $e) {
            // Si falla, continuar - el query lo intentará
        }

        // SOQL para obtener proyectos desde Proyecto__c
        // Nota: Usamos Fecha_Recepcion_Municipal__c como proxy para fecha de entrega
        $soql = 'SELECT Id, Name, Descripci_n__c, Direccion__c, Comuna__c, Provincia__c, Region__c, '
            .'Email__c, Telefono__c, Pagina_Web_Proyecto__c, Razon_Social__c, RUT__c, '
            .'Fecha_Inicio_Ventas__c, Fecha_Recepcion_Municipal__c, Etapa__c, Horario_Atencion__c, '
            .'Dscto_M_x_Prod_Principal_Porc__c, Dscto_M_x_Prod_Principal_UF__c, '
            .'Dscto_M_x_Bodega_Porc__c, Dscto_M_x_Bodega_UF__c, '
            .'Dscto_M_x_Estac_Porc__c, Dscto_M_x_Estac_UF__c, '
            .'Dscto_Max_Otros_Porc__c, Dscto_Max_Otros_Prod_UF__c, '
            .'Dscto_Maximo_Aporte_Leben__c, '
            .'N_A_os_1__c, N_A_os_2__c, N_A_os_3__c, N_A_os_4__c, '
            .'Valor_Reserva_Exigido_Defecto_Peso__c, Valor_Reserva_Exigido_Min_Peso__c, '
            .'Tasa__c, Entrega_Inmediata__c '
            .'FROM Proyecto__c '
            ."WHERE IsDeleted = false AND Activo__c = true AND Tipo_Producto__c = 'DEPARTAMENTO' "
            .'ORDER BY Name '
            .'LIMIT 1000';

        $ttl = $cacheTtl ?? $this->defaultCacheTtl;

        return Cache::remember('salesforce:proyectos', $ttl, function () use ($soql) {
            try {
                $result = Forrest::query($soql);
                $entries = $result['records'] ?? [];

                // Transformar estructura Salesforce a formato más amigable
                return array_map(function ($entry) {
                    return [
                        'id' => $entry['Id'] ?? null,
                        'name' => $entry['Name'] ?? null,
                        'descripcion' => $entry['Descripci_n__c'] ?? null,
                        'direccion' => $entry['Direccion__c'] ?? null,
                        'comuna' => $entry['Comuna__c'] ?? null,
                        'provincia' => $entry['Provincia__c'] ?? null,
                        'region' => $entry['Region__c'] ?? null,
                        'email' => $entry['Email__c'] ?? null,
                        'telefono' => $entry['Telefono__c'] ?? null,
                        'pagina_web' => $entry['Pagina_Web_Proyecto__c'] ?? null,
                        'razon_social' => $entry['Razon_Social__c'] ?? null,
                        'rut' => $entry['RUT__c'] ?? null,
                        'fecha_inicio_ventas' => $entry['Fecha_Inicio_Ventas__c'] ?? null,
                        'fecha_entrega' => $entry['Fecha_Recepcion_Municipal__c'] ?? null,
                        'etapa' => $entry['Etapa__c'] ?? null,
                        'horario_atencion' => $entry['Horario_Atencion__c'] ?? null,
                        'dscto_m_x_prod_principal_porc' => (float) ($entry['Dscto_M_x_Prod_Principal_Porc__c'] ?? 0),
                        'dscto_m_x_prod_principal_uf' => (float) ($entry['Dscto_M_x_Prod_Principal_UF__c'] ?? 0),
                        'dscto_m_x_bodega_porc' => (float) ($entry['Dscto_M_x_Bodega_Porc__c'] ?? 0),
                        'dscto_m_x_bodega_uf' => (float) ($entry['Dscto_M_x_Bodega_UF__c'] ?? 0),
                        'dscto_m_x_estac_porc' => (float) ($entry['Dscto_M_x_Estac_Porc__c'] ?? 0),
                        'dscto_m_x_estac_uf' => (float) ($entry['Dscto_M_x_Estac_UF__c'] ?? 0),
                        'dscto_max_otros_porc' => (float) ($entry['Dscto_Max_Otros_Porc__c'] ?? 0),
                        'dscto_max_otros_prod_uf' => (float) ($entry['Dscto_Max_Otros_Prod_UF__c'] ?? 0),
                        'dscto_maximo_aporte_leben' => (float) ($entry['Dscto_Maximo_Aporte_Leben__c'] ?? 0),
                        'n_anos_1' => $entry['N_A_os_1__c'] ? (int) $entry['N_A_os_1__c'] : null,
                        'n_anos_2' => $entry['N_A_os_2__c'] ? (int) $entry['N_A_os_2__c'] : null,
                        'n_anos_3' => $entry['N_A_os_3__c'] ? (int) $entry['N_A_os_3__c'] : null,
                        'n_anos_4' => $entry['N_A_os_4__c'] ? (int) $entry['N_A_os_4__c'] : null,
                        'valor_reserva_exigido_defecto_peso' => $entry['Valor_Reserva_Exigido_Defecto_Peso__c'] ? (float) $entry['Valor_Reserva_Exigido_Defecto_Peso__c'] : null,
                        'valor_reserva_exigido_min_peso' => $entry['Valor_Reserva_Exigido_Min_Peso__c'] ? (float) $entry['Valor_Reserva_Exigido_Min_Peso__c'] : null,
                        'tasa' => $entry['Tasa__c'] ? (float) $entry['Tasa__c'] : null,
                        'entrega_inmediata' => (bool) ($entry['Entrega_Inmediata__c'] ?? false),
                    ];
                }, $entries);
            } catch (MissingKeyException $e) {
                $this->authenticate();
                $result = Forrest::query($soql);
                $entries = $result['records'] ?? [];

                return array_map(function ($entry) {
                    return [
                        'id' => $entry['Id'] ?? null,
                        'name' => $entry['Name'] ?? null,
                        'descripcion' => $entry['Descripci_n__c'] ?? null,
                        'direccion' => $entry['Direccion__c'] ?? null,
                        'comuna' => $entry['Comuna__c'] ?? null,
                        'provincia' => $entry['Provincia__c'] ?? null,
                        'region' => $entry['Region__c'] ?? null,
                        'email' => $entry['Email__c'] ?? null,
                        'telefono' => $entry['Telefono__c'] ?? null,
                        'pagina_web' => $entry['Pagina_Web_Proyecto__c'] ?? null,
                        'razon_social' => $entry['Razon_Social__c'] ?? null,
                        'rut' => $entry['RUT__c'] ?? null,
                        'fecha_inicio_ventas' => $entry['Fecha_Inicio_Ventas__c'] ?? null,
                        'fecha_entrega' => $entry['Fecha_Recepcion_Municipal__c'] ?? null,
                        'etapa' => $entry['Etapa__c'] ?? null,
                        'horario_atencion' => $entry['Horario_Atencion__c'] ?? null,
                        'dscto_m_x_prod_principal_porc' => (float) ($entry['Dscto_M_x_Prod_Principal_Porc__c'] ?? 0),
                        'dscto_m_x_prod_principal_uf' => (float) ($entry['Dscto_M_x_Prod_Principal_UF__c'] ?? 0),
                        'dscto_m_x_bodega_porc' => (float) ($entry['Dscto_M_x_Bodega_Porc__c'] ?? 0),
                        'dscto_m_x_bodega_uf' => (float) ($entry['Dscto_M_x_Bodega_UF__c'] ?? 0),
                        'dscto_m_x_estac_porc' => (float) ($entry['Dscto_M_x_Estac_Porc__c'] ?? 0),
                        'dscto_m_x_estac_uf' => (float) ($entry['Dscto_M_x_Estac_UF__c'] ?? 0),
                        'dscto_max_otros_porc' => (float) ($entry['Dscto_Max_Otros_Porc__c'] ?? 0),
                        'dscto_max_otros_prod_uf' => (float) ($entry['Dscto_Max_Otros_Prod_UF__c'] ?? 0),
                        'dscto_maximo_aporte_leben' => (float) ($entry['Dscto_Maximo_Aporte_Leben__c'] ?? 0),
                        'n_anos_1' => $entry['N_A_os_1__c'] ? (int) $entry['N_A_os_1__c'] : null,
                        'n_anos_2' => $entry['N_A_os_2__c'] ? (int) $entry['N_A_os_2__c'] : null,
                        'n_anos_3' => $entry['N_A_os_3__c'] ? (int) $entry['N_A_os_3__c'] : null,
                        'n_anos_4' => $entry['N_A_os_4__c'] ? (int) $entry['N_A_os_4__c'] : null,
                        'valor_reserva_exigido_defecto_peso' => $entry['Valor_Reserva_Exigido_Defecto_Peso__c'] ? (float) $entry['Valor_Reserva_Exigido_Defecto_Peso__c'] : null,
                        'valor_reserva_exigido_min_peso' => $entry['Valor_Reserva_Exigido_Min_Peso__c'] ? (float) $entry['Valor_Reserva_Exigido_Min_Peso__c'] : null,
                        'tasa' => $entry['Tasa__c'] ? (float) $entry['Tasa__c'] : null,
                        'entrega_inmediata' => (bool) ($entry['Entrega_Inmediata__c'] ?? false),
                    ];
                }, $entries);
            }
        });
    }

    /**
     * Invalidar caché de proyectos
     */
    public function invalidateProjectsCache(): void
    {
        Cache::forget('salesforce:proyectos');
    }
}
