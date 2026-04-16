<?php

namespace App\Services\Salesforce;

use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
            } catch (\Throwable $e) {
                // Re-autenticar si el token expiró o no hay recursos disponibles
                Log::info('Salesforce: Re-autenticando debido a: '.$e->getMessage());
                $this->authenticate();
                $result = Forrest::query($soql);

                return $result['records'] ?? [];
            }
        });
    }

    /**
     * Crear un Case en Salesforce.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCase(array $payload): array
    {
        try {
            $result = Forrest::sobjects('Case', [
                'method' => 'post',
                'body' => $payload,
            ]);

            Log::info('Salesforce: Case creado', [
                'case_id' => $result['id'] ?? $result['Id'] ?? null,
                'subject' => $payload['Subject'] ?? null,
            ]);

            return is_array($result) ? $result : [];
        } catch (\Throwable $e) {
            Log::info('Salesforce: Re-autenticando Case debido a: '.$e->getMessage());
            $this->authenticate();

            $result = Forrest::sobjects('Case', [
                'method' => 'post',
                'body' => $payload,
            ]);

            Log::info('Salesforce: Case creado tras re-autenticación', [
                'case_id' => $result['id'] ?? $result['Id'] ?? null,
                'subject' => $payload['Subject'] ?? null,
            ]);

            return is_array($result) ? $result : [];
        }
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
     *               'modelo_name' => string|null,
     *               'modelo_programa' => string|null,
     *               'programa' => string,
     *               'programa2' => string,
     *               'piso' => string,
     *               'precio_base' => float,
     *               'precio_lista' => float,
     *               'porcentaje_maximo_unidad' => float,
     *               'superficie_total_principal' => float,
     *               'superficie_interior' => float,
     *               'superficie_util' => float,
     *               'superficie_terraza' => float,
     *               ]
     */
    /**
     * @param  list<string>|null  $projectSalesforceIds
     */
    public function findPlants(?int $cacheTtl = null, ?array $projectSalesforceIds = null): array
    {
        $productTypes = $this->getConfiguredPlantProductTypes();
        $productTypesInClause = implode(',', array_map(
            static fn (string $type): string => "'".str_replace("'", "\\'", $type)."'",
            $productTypes
        ));
        $projectIds = $this->normalizeSalesforceIdList($projectSalesforceIds ?? []);

        if ($projectIds === []) {
            return [];
        }

        $projectIdsInClause = implode(',', array_map(
            static fn (string $id): string => "'".str_replace("'", "\\'", $id)."'",
            $projectIds
        ));

        // SOQL para obtener plantas desde Product2
        $soql = 'SELECT Id, Name, ProductCode, Orientacion2__c, Programa__c, Programa2__c, Modelo__r.Name, Modelo__r.Programa__c, Piso__c, '
            .'Precio_Base__c, Precio_Lista__c, Porcentaje_maximo_de_unidad__c, '
            .'Superficie_Total_Producto_Principal__c, Superficie_Interior__c, Superficie_Util__c, '
            .'Superficie_Terraza__c, Proyecto__c, Tipo_Producto__c '
            .'FROM Product2 '
            ."WHERE IsActive = true AND Estado__c = 'Disponible' AND Tipo_Producto__c IN ({$productTypesInClause}) AND Proyecto__c IN ({$projectIdsInClause}) "
            .'ORDER BY Name '
            .'LIMIT 1000';

        $ttl = $cacheTtl ?? $this->defaultCacheTtl;
        $cacheKey = $this->buildPlantsCacheKey($productTypes, $projectIds);

        return Cache::remember($cacheKey, $ttl, function () use ($soql) {
            try {
                $result = Forrest::query($soql);
                $entries = $result['records'] ?? [];

                // Transformar estructura Salesforce a formato más amigable
                return array_map(function ($entry) {
                    return [
                        'id' => $entry['Id'] ?? null,
                        'name' => $entry['Name'] ?? null,
                        'product_code' => $entry['ProductCode'] ?? null,
                        'tipo_producto' => $entry['Tipo_Producto__c'] ?? null,
                        'orientacion' => $entry['Orientacion2__c'] ?? null,
                        'modelo_name' => $entry['Modelo__r']['Name'] ?? null,
                        'modelo_programa' => $entry['Modelo__r']['Programa__c'] ?? null,
                        'programa' => $entry['Programa__c'] ?? null,
                        'programa2' => $entry['Programa2__c'] ?? null,
                        'piso' => $entry['Piso__c'] ?? null,
                        'precio_base' => (float) ($entry['Precio_Base__c'] ?? 0) ?: 0,
                        'precio_lista' => (float) ($entry['Precio_Lista__c'] ?? 0) ?: 0,
                        'porcentaje_maximo_unidad' => (float) ($entry['Porcentaje_maximo_de_unidad__c'] ?? 0) ?: 0,
                        'superficie_total_principal' => (float) ($entry['Superficie_Total_Producto_Principal__c'] ?? 0),
                        'superficie_interior' => (float) ($entry['Superficie_Interior__c'] ?? 0),
                        'superficie_util' => (float) ($entry['Superficie_Util__c'] ?? 0),
                        'superficie_terraza' => (float) ($entry['Superficie_Terraza__c'] ?? 0),
                        'proyecto_id' => $entry['Proyecto__c'] ?? null,
                    ];
                }, $entries);
            } catch (\Throwable $e) {
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
                        'tipo_producto' => $entry['Tipo_Producto__c'] ?? null,
                        'orientacion' => $entry['Orientacion2__c'] ?? null,
                        'modelo_name' => $entry['Modelo__r']['Name'] ?? null,
                        'modelo_programa' => $entry['Modelo__r']['Programa__c'] ?? null,
                        'programa' => $entry['Programa__c'] ?? null,
                        'programa2' => $entry['Programa2__c'] ?? null,
                        'piso' => $entry['Piso__c'] ?? null,
                        'precio_base' => (float) ($entry['Precio_Base__c'] ?? 0) ?: 0,
                        'precio_lista' => (float) ($entry['Precio_Lista__c'] ?? 0) ?: 0,
                        'porcentaje_maximo_unidad' => (float) ($entry['Porcentaje_maximo_de_unidad__c'] ?? 0) ?: 0,
                        'superficie_total_principal' => (float) ($entry['Superficie_Total_Producto_Principal__c'] ?? 0),
                        'superficie_interior' => (float) ($entry['Superficie_Interior__c'] ?? 0),
                        'superficie_util' => (float) ($entry['Superficie_Util__c'] ?? 0),
                        'superficie_terraza' => (float) ($entry['Superficie_Terraza__c'] ?? 0),
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
     * @return list<string>
     */
    private function getConfiguredPlantProductTypes(): array
    {
        $configuredTypes = SiteSetting::get('salesforce_sync_plant_types', ['DEPARTAMENTO']);

        if (! is_array($configuredTypes)) {
            return ['DEPARTAMENTO'];
        }

        $normalizedTypes = array_values(array_unique(array_filter(array_map(
            static fn (mixed $type): string => strtoupper(trim((string) $type)),
            $configuredTypes
        ), static fn (string $type): bool => $type !== '')));

        return $normalizedTypes === [] ? ['DEPARTAMENTO'] : $normalizedTypes;
    }

    /**
     * @param  list<string>  $productTypes
     * @param  list<string>  $projectSalesforceIds
     */
    private function buildPlantsCacheKey(array $productTypes, array $projectSalesforceIds): string
    {
        return 'salesforce:plants:'.md5(implode('|', $productTypes).'::'.implode('|', $projectSalesforceIds));
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
     *               'valor_reserva_exigido_defecto_peso' => float|null,
     *               'valor_reserva_exigido_min_peso' => float|null,
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
            .'Asesor_Responsable__c, '
            .'Valor_Reserva_Exigido_Defecto_Peso__c, Valor_Reserva_Exigido_Min_Peso__c, '
            .'Entrega_Inmediata__c '
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
                        'asesor_responsable_ids' => $this->normalizeSalesforceIdList($entry['Asesor_Responsable__c'] ?? null),
                        'valor_reserva_exigido_defecto_peso' => $entry['Valor_Reserva_Exigido_Defecto_Peso__c'] ? (float) $entry['Valor_Reserva_Exigido_Defecto_Peso__c'] : null,
                        'valor_reserva_exigido_min_peso' => $entry['Valor_Reserva_Exigido_Min_Peso__c'] ? (float) $entry['Valor_Reserva_Exigido_Min_Peso__c'] : null,
                        'entrega_inmediata' => (bool) ($entry['Entrega_Inmediata__c'] ?? false),
                    ];
                }, $entries);
            } catch (\Throwable $e) {
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
                        'asesor_responsable_ids' => $this->normalizeSalesforceIdList($entry['Asesor_Responsable__c'] ?? null),
                        'valor_reserva_exigido_defecto_peso' => $entry['Valor_Reserva_Exigido_Defecto_Peso__c'] ? (float) $entry['Valor_Reserva_Exigido_Defecto_Peso__c'] : null,
                        'valor_reserva_exigido_min_peso' => $entry['Valor_Reserva_Exigido_Min_Peso__c'] ? (float) $entry['Valor_Reserva_Exigido_Min_Peso__c'] : null,
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

    /**
     * Obtener usuarios de Salesforce por IDs.
     *
     * @param  list<string>  $salesforceUserIds
     * @return list<array{id: string|null, first_name: string|null, last_name: string|null, email: string|null, whatsapp_owner: string|null, avatar_url: string|null, is_active: bool}>
     */
    public function findSalesforceUsersByIds(array $salesforceUserIds, ?int $cacheTtl = null): array
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map(
            static fn (string $id): string => trim($id),
            $salesforceUserIds
        ), static fn (string $id): bool => $id !== '')));

        if ($normalizedIds === []) {
            return [];
        }

        $quotedIds = array_map(
            static fn (string $id): string => "'".str_replace("'", "\\'", $id)."'",
            $normalizedIds
        );

        $soql = 'SELECT Id, FirstName, LastName, Email, Whatsapp_owner__c, MediumPhotoUrl, IsActive '
            .'FROM User '
            .'WHERE Id IN ('.implode(',', $quotedIds).') '
            .'LIMIT 2000';

        $records = $this->query($soql, $cacheTtl ?? $this->defaultCacheTtl);

        return array_map(static function (array $entry): array {
            return [
                'id' => $entry['Id'] ?? null,
                'first_name' => $entry['FirstName'] ?? null,
                'last_name' => $entry['LastName'] ?? null,
                'email' => $entry['Email'] ?? null,
                'whatsapp_owner' => $entry['Whatsapp_owner__c'] ?? null,
                'avatar_url' => $entry['MediumPhotoUrl'] ?? null,
                'is_active' => (bool) ($entry['IsActive'] ?? true),
            ];
        }, $records);
    }

    /**
     * @return list<string>
     */
    private function normalizeSalesforceIdList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map(
                static fn (mixed $item): string => trim((string) $item),
                $value
            ), static fn (string $item): bool => $item !== '')));
        }

        $asString = trim((string) $value);
        if ($asString === '') {
            return [];
        }

        if (str_contains($asString, ';')) {
            $parts = explode(';', $asString);

            return array_values(array_unique(array_filter(array_map(
                static fn (string $item): string => trim($item),
                $parts
            ), static fn (string $item): bool => $item !== '')));
        }

        if (str_contains($asString, ',')) {
            $parts = explode(',', $asString);

            return array_values(array_unique(array_filter(array_map(
                static fn (string $item): string => trim($item),
                $parts
            ), static fn (string $item): bool => $item !== '')));
        }

        return [$asString];
    }

    /**
     * Obtener documentos públicos de Salesforce para branding de cotizador (logo/portada).
     *
     * @param  list<string>  $documentNames
     * @return list<array{
     *   id: string|null,
     *   name: string|null,
     *   type: string|null,
     *   body_length: int,
     *   body_path: string|null,
     *   download_url: string|null,
     *   project_name: string|null,
     *   asset_kind: string|null,
     *   last_modified_at: string|null
     * }>
     */
    public function findPublicProjectDocuments(array $documentNames, ?int $cacheTtl = null): array
    {
        $names = array_values(array_unique(array_filter(array_map(
            static fn ($name): string => trim((string) $name),
            $documentNames
        ), static fn (string $name): bool => $name !== '')));

        if ($names === []) {
            return [];
        }

        $quotedNames = array_map(
            static fn (string $name): string => "'".str_replace("'", "\\'", $name)."'",
            $names
        );

        $soql = 'SELECT Id, Name, Type, BodyLength, Body, LastModifiedDate FROM Document '
            .'WHERE IsPublic = true AND Name IN ('.implode(',', $quotedNames).') '
            .'ORDER BY Name';

        $ttl = $cacheTtl ?? $this->defaultCacheTtl;
        $records = $this->query($soql, $ttl);

        return $this->mapPublicProjectDocuments($records);
    }

    /**
     * Obtener todos los documentos públicos de cotizador (logo/portada) sin lista fija de nombres.
     *
     * @return list<array{
     *   id: string|null,
     *   name: string|null,
     *   type: string|null,
     *   body_length: int,
     *   body_path: string|null,
     *   download_url: string|null,
     *   project_name: string|null,
     *   asset_kind: string|null,
     *   last_modified_at: string|null
     * }>
     */
    public function findPublicCotizadorDocuments(?int $cacheTtl = null): array
    {
        $soql = 'SELECT Id, Name, Type, BodyLength, Body, LastModifiedDate FROM Document '
            ."WHERE IsPublic = true AND (Name LIKE '% - Cotizador Portada' OR Name LIKE '% - Cotizador Logo') "
            .'ORDER BY Name';

        $ttl = $cacheTtl ?? $this->defaultCacheTtl;
        $records = $this->query($soql, $ttl);

        return $this->mapPublicProjectDocuments($records);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<array{
     *   id: string|null,
     *   name: string|null,
     *   type: string|null,
     *   body_length: int,
     *   body_path: string|null,
     *   download_url: string|null,
     *   project_name: string|null,
     *   asset_kind: string|null,
     *   last_modified_at: string|null
     * }>
     */
    private function mapPublicProjectDocuments(array $records): array
    {
        return array_map(function (array $record): array {
            $name = $record['Name'] ?? null;
            $bodyPath = $record['Body'] ?? null;
            $documentId = $record['Id'] ?? null;
            $lastModifiedAt = $record['LastModifiedDate'] ?? null;

            return [
                'id' => $documentId,
                'name' => $name,
                'type' => $record['Type'] ?? null,
                'body_length' => (int) ($record['BodyLength'] ?? 0),
                'body_path' => $bodyPath,
                'download_url' => $this->buildSalesforceDownloadUrl($bodyPath, $documentId, $lastModifiedAt),
                'project_name' => $this->extractProjectNameFromDocumentName($name),
                'asset_kind' => $this->extractAssetKindFromDocumentName($name),
                'last_modified_at' => $lastModifiedAt,
            ];
        }, $records);
    }

    /**
     * @return list<string>
     */
    public function getDefaultProjectDocumentNames(): array
    {
        return [
            'Edificio Indi - Cotizador Portada',
            'Edificio Indi - Cotizador Logo',
            'Edificio Capitanes - Cotizador Portada',
            'Edificio Capitanes - Cotizador Logo',
        ];
    }

    private function buildSalesforceDownloadUrl(?string $bodyPath, ?string $documentId = null, ?string $lastModifiedAt = null): ?string
    {
        $publicSiteUrl = $this->resolvePublicSiteUrl();
        $orgId = $this->resolveOrgId();

        if ($documentId !== null && trim($documentId) !== '' && $publicSiteUrl !== null && $orgId !== null) {
            $query = [
                'id' => $documentId,
                'oid' => $orgId,
            ];

            $lastMod = $this->toLastModMillis($lastModifiedAt);
            if ($lastMod !== null) {
                $query['lastMod'] = $lastMod;
            }

            return rtrim($publicSiteUrl, '/').'/servlet/servlet.ImageServer?'.http_build_query($query);
        }

        if ($bodyPath === null || trim($bodyPath) === '') {
            return null;
        }

        $instanceUrl = (string) config('services.salesforce.instance_url', '');
        if ($instanceUrl === '') {
            return null;
        }

        return rtrim($instanceUrl, '/').'/'.ltrim($bodyPath, '/');
    }

    private function resolvePublicSiteUrl(): ?string
    {
        $configuredSiteUrl = trim((string) config('services.salesforce.public_site_url', ''));
        if ($configuredSiteUrl !== '') {
            return rtrim($configuredSiteUrl, '/');
        }

        $instanceUrl = trim((string) config('services.salesforce.instance_url', ''));
        if ($instanceUrl === '') {
            return null;
        }

        $parts = parse_url($instanceUrl);
        $host = $parts['host'] ?? null;
        if (! is_string($host) || $host === '') {
            return null;
        }

        $siteHost = str_replace('salesforce.com', 'salesforce-sites.com', $host);
        if ($siteHost === '' || $siteHost === $host) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';

        return sprintf('%s://%s', $scheme, $siteHost);
    }

    private function resolveOrgId(): ?string
    {
        $configuredOrgId = trim((string) config('services.salesforce.org_id', ''));
        if ($configuredOrgId !== '') {
            return $configuredOrgId;
        }

        $cacheKey = 'salesforce:org_id:auto';
        $cachedOrgId = Cache::get($cacheKey);
        if (is_string($cachedOrgId) && trim($cachedOrgId) !== '') {
            return $cachedOrgId;
        }

        $resolvedOrgId = $this->resolveOrgIdFromIdentity() ?? $this->resolveOrgIdFromOrganizationQuery();
        if ($resolvedOrgId !== null) {
            Cache::put($cacheKey, $resolvedOrgId, now()->addDay());
        }

        return $resolvedOrgId;
    }

    private function resolveOrgIdFromIdentity(): ?string
    {
        try {
            $identity = Forrest::identity();

            $identityUrl = null;
            if (is_array($identity)) {
                $identityUrl = $identity['id'] ?? $identity['identity'] ?? null;
            } elseif (is_string($identity)) {
                $identityUrl = $identity;
            }

            if (! is_string($identityUrl) || $identityUrl === '') {
                return null;
            }

            if (preg_match('/\/id\/([a-zA-Z0-9]{15,18})\//', $identityUrl, $matches) === 1) {
                return $matches[1];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function resolveOrgIdFromOrganizationQuery(): ?string
    {
        try {
            $result = Forrest::query('SELECT Id FROM Organization LIMIT 1');
            $records = $result['records'] ?? [];
            $orgId = $records[0]['Id'] ?? null;

            return is_string($orgId) && trim($orgId) !== '' ? $orgId : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function toLastModMillis(?string $lastModifiedAt): ?string
    {
        if ($lastModifiedAt === null || trim($lastModifiedAt) === '') {
            return null;
        }

        try {
            return (string) Carbon::parse($lastModifiedAt)->getTimestampMs();
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractProjectNameFromDocumentName(?string $documentName): ?string
    {
        if ($documentName === null || trim($documentName) === '') {
            return null;
        }

        return preg_replace('/\s*-\s*Cotizador\s*(Logo|Portada)\s*$/i', '', $documentName) ?: $documentName;
    }

    private function extractAssetKindFromDocumentName(?string $documentName): ?string
    {
        if ($documentName === null) {
            return null;
        }

        if (stripos($documentName, 'Cotizador Logo') !== false) {
            return 'logo';
        }

        if (stripos($documentName, 'Cotizador Portada') !== false) {
            return 'portada';
        }

        return null;
    }
}
