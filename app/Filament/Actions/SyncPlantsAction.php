<?php

namespace App\Filament\Actions;

use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Services\Salesforce\SalesforceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncPlantsAction
{
    /**
     * @var array<string, string>
     */
    private const UPDATABLE_PLANT_FIELD_OPTIONS = [
        'salesforce_proyecto_id' => 'Proyecto Salesforce',
        'name' => 'Nombre',
        'tipo_producto' => 'Tipo producto',
        'orientacion' => 'Orientacion',
        'programa' => 'Programa',
        'programa2' => 'Programa 2',
        'piso' => 'Piso',
        'precio_base' => 'Precio base',
        'precio_lista' => 'Precio lista',
        'porcentaje_maximo_unidad' => 'Porcentaje maximo unidad',
        'superficie_total_principal' => 'Superficie total principal',
        'superficie_interior' => 'Superficie interior',
        'superficie_util' => 'Superficie util',
        'superficie_terraza' => 'Superficie terraza',
        'salesforce_interior_image_url' => 'URL interior Salesforce',
    ];

    /**
     * @return array<string, string>
     */
    public static function getUpdatableFieldOptions(): array
    {
        return self::UPDATABLE_PLANT_FIELD_OPTIONS;
    }

    /**
     * Crear acción para Filament
     */
    public static function make(): Action
    {
        return Action::make('sync_plants')
            ->label('Sincronizar Plantas')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->action(function () {
                $result = self::execute();

                if (($result['success'] ?? false) === true) {
                    Notification::make()
                        ->title('Sincronizacion de plantas completada')
                        ->body((string) ($result['message'] ?? 'Sincronizacion completada.'))
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Error al sincronizar plantas')
                    ->body((string) ($result['message'] ?? 'Ocurrio un error durante la sincronizacion.'))
                    ->danger()
                    ->send();
            });
    }

    /**
     * Sincronizar plantas desde Salesforce a la base de datos local
     */
    public static function execute(): array
    {
        try {
            Log::info('Iniciando sincronización de plantas desde Salesforce...');
            $excludedUpdateFields = self::resolveExcludedUpdateFields();

            $salesforceService = app(SalesforceService::class);
            $projectNamesBySalesforceId = Proyecto::query()
                ->whereNotNull('salesforce_id')
                ->pluck('name', 'salesforce_id')
                ->toArray();
            $projectSalesforceIds = array_values(array_keys($projectNamesBySalesforceId));

            if ($projectSalesforceIds === []) {
                Log::warning('No existen proyectos locales con salesforce_id para sincronizar plantas.');

                return [
                    'success' => false,
                    'message' => 'No existen proyectos locales sincronizados para importar plantas.',
                    'count' => 0,
                ];
            }

            $plants = $salesforceService->findPlants(projectSalesforceIds: $projectSalesforceIds);

            $documentNames = self::buildPlantInteriorDocumentNames($plants, $projectNamesBySalesforceId);
            $interiorImageUrlsByDocumentName = self::buildInteriorImageUrlsByDocumentName($salesforceService, $documentNames);

            Log::info('Plantas obtenidas de Salesforce: '.count($plants));

            if (empty($plants)) {
                Log::warning('No se encontraron plantas en Salesforce');

                return [
                    'success' => false,
                    'message' => 'No se encontraron plantas en Salesforce',
                    'count' => 0,
                ];
            }

            $synced = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($plants as $plantData) {
                if (empty($plantData['proyecto_id'])) {
                    $skipped++;

                    continue;
                }

                $tipoProducto = self::resolveTipoProducto($plantData['tipo_producto'] ?? null);

                $salesforceInteriorImageUrl = self::resolvePlantInteriorImageUrl(
                    $plantData,
                    $projectNamesBySalesforceId,
                    $interiorImageUrlsByDocumentName,
                );

                $existingPlant = Plant::where('salesforce_product_id', $plantData['id'])->first();

                if ($existingPlant) {
                    // Update sin product_code (preservar el existente)
                    $updateData = [
                        'salesforce_proyecto_id' => $plantData['proyecto_id'],
                        'name' => $plantData['name'],
                        'tipo_producto' => $tipoProducto,
                        'orientacion' => $plantData['orientacion'],
                        'programa' => $plantData['programa'],
                        'programa2' => $plantData['programa2'],
                        'piso' => $plantData['piso'],
                        'precio_base' => $plantData['precio_base'],
                        'precio_lista' => $plantData['precio_lista'],
                        'porcentaje_maximo_unidad' => $plantData['porcentaje_maximo_unidad'] ?? null,
                        'superficie_total_principal' => $plantData['superficie_total_principal'],
                        'superficie_interior' => $plantData['superficie_interior'],
                        'superficie_util' => $plantData['superficie_util'],
                        'superficie_terraza' => $plantData['superficie_terraza'],
                        'is_active' => true,
                        'last_synced_at' => Carbon::now(),
                    ];

                    if (is_string($salesforceInteriorImageUrl) && trim($salesforceInteriorImageUrl) !== '') {
                        $updateData['salesforce_interior_image_url'] = $salesforceInteriorImageUrl;
                    }

                    $existingPlant->update(self::removeExcludedUpdateFields($updateData, $excludedUpdateFields));
                    $updated++;
                } else {
                    // Create con product_code
                    $createData = [
                        'salesforce_product_id' => $plantData['id'],
                        'salesforce_proyecto_id' => $plantData['proyecto_id'],
                        'name' => $plantData['name'],
                        'product_code' => $plantData['product_code'],
                        'tipo_producto' => $tipoProducto,
                        'orientacion' => $plantData['orientacion'],
                        'programa' => $plantData['programa'],
                        'programa2' => $plantData['programa2'],
                        'piso' => $plantData['piso'],
                        'precio_base' => $plantData['precio_base'],
                        'precio_lista' => $plantData['precio_lista'],
                        'porcentaje_maximo_unidad' => $plantData['porcentaje_maximo_unidad'] ?? null,
                        'superficie_total_principal' => $plantData['superficie_total_principal'],
                        'superficie_interior' => $plantData['superficie_interior'],
                        'superficie_util' => $plantData['superficie_util'],
                        'superficie_terraza' => $plantData['superficie_terraza'],
                        'is_active' => true,
                        'last_synced_at' => Carbon::now(),
                    ];

                    if (is_string($salesforceInteriorImageUrl) && trim($salesforceInteriorImageUrl) !== '') {
                        $createData['salesforce_interior_image_url'] = $salesforceInteriorImageUrl;
                    }

                    Plant::create($createData);
                    $synced++;
                }
            }

            Log::info("Sincronización completada. {$synced} nuevas plantas, {$updated} actualizadas, {$skipped} sin proyecto o sin proyecto local");

            return [
                'success' => true,
                'message' => "Sincronización completada. {$synced} nuevas plantas, {$updated} actualizadas, {$skipped} sin proyecto o sin proyecto local",
                'count' => $synced + $updated,
                'created' => $synced,
                'updated' => $updated,
                'skipped' => $skipped,
            ];
        } catch (\Exception $e) {
            Log::error('Error al sincronizar plantas: '.$e->getMessage(), [
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al sincronizar: '.$e->getMessage(),
                'count' => 0,
            ];
        }
    }

    private static function resolveTipoProducto(mixed $tipoProducto): string
    {
        $normalized = strtoupper(trim((string) $tipoProducto));

        return $normalized !== '' ? $normalized : 'DEPARTAMENTO';
    }

    /**
     * Obtener el timestamp de última sincronización
     */
    public static function getLastSyncTime(): ?Carbon
    {
        return Plant::latest('last_synced_at')->first()?->last_synced_at;
    }

    /**
     * Obtener cantidad total de plantas
     */
    public static function getTotalPlants(): int
    {
        return Plant::count();
    }

    /**
     * Obtener cantidad de plantas activas
     */
    public static function getActivePlants(): int
    {
        return Plant::active()->count();
    }

    /**
     * @param  list<array<string, mixed>>  $plants
     * @param  array<string, string>  $projectNamesBySalesforceId
     * @return list<string>
     */
    private static function buildPlantInteriorDocumentNames(array $plants, array $projectNamesBySalesforceId): array
    {
        $documentNames = [];

        foreach ($plants as $plantData) {
            $projectSalesforceId = trim((string) ($plantData['proyecto_id'] ?? ''));
            $projectName = trim((string) ($projectNamesBySalesforceId[$projectSalesforceId] ?? ''));
            if ($projectName === '') {
                continue;
            }

            foreach (self::extractPlantDocumentIdentifiers($plantData) as $identifier) {
                if ($identifier === '') {
                    continue;
                }

                $documentNames[] = self::buildPlantDocumentName($projectName, $identifier);
            }
        }

        return array_values(array_unique($documentNames));
    }

    /**
     * @param  list<string>  $documentNames
     * @return array<string, string>
     */
    private static function buildInteriorImageUrlsByDocumentName(SalesforceService $salesforceService, array $documentNames): array
    {
        if ($documentNames === []) {
            return [];
        }

        $documentsByName = [];

        foreach (array_chunk($documentNames, 100) as $chunk) {
            $documents = $salesforceService->findPublicProjectDocuments($chunk);

            foreach ($documents as $document) {
                $name = trim((string) ($document['name'] ?? ''));
                $downloadUrl = trim((string) ($document['download_url'] ?? ''));

                if ($name === '' || $downloadUrl === '') {
                    continue;
                }

                $documentsByName[self::normalizeDocumentName($name)] = $downloadUrl;
            }
        }

        return $documentsByName;
    }

    /**
     * @param  array<string, mixed>  $plantData
     * @param  array<string, string>  $projectNamesBySalesforceId
     * @param  array<string, string>  $interiorImageUrlsByDocumentName
     */
    private static function resolvePlantInteriorImageUrl(
        array $plantData,
        array $projectNamesBySalesforceId,
        array $interiorImageUrlsByDocumentName,
    ): ?string {
        $projectSalesforceId = trim((string) ($plantData['proyecto_id'] ?? ''));
        $projectName = trim((string) ($projectNamesBySalesforceId[$projectSalesforceId] ?? ''));

        if ($projectName === '') {
            return null;
        }

        foreach (self::extractPlantDocumentIdentifiers($plantData) as $identifier) {
            $documentName = self::normalizeDocumentName(self::buildPlantDocumentName($projectName, $identifier));

            if (isset($interiorImageUrlsByDocumentName[$documentName])) {
                return $interiorImageUrlsByDocumentName[$documentName];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $plantData
     * @return list<string>
     */
    private static function extractPlantDocumentIdentifiers(array $plantData): array
    {
        $identifiers = [];

        $modelBasedIdentifier = self::buildModelBasedIdentifier($plantData);
        if ($modelBasedIdentifier !== null) {
            $identifiers[] = $modelBasedIdentifier;
        }

        $plantName = trim((string) ($plantData['name'] ?? ''));
        if ($plantName !== '') {
            $identifiers[] = $plantName;
        }

        $productCode = trim((string) ($plantData['product_code'] ?? ''));
        if ($productCode !== '') {
            $identifiers[] = $productCode;

            if (str_contains($productCode, ' - ')) {
                $suffix = trim((string) strstr($productCode, ' - '));
                $suffix = ltrim($suffix, ' -');

                if ($suffix !== '') {
                    $identifiers[] = $suffix;
                }
            }
        }

        return array_values(array_unique($identifiers));
    }

    /**
     * @param  array<string, mixed>  $plantData
     */
    private static function buildModelBasedIdentifier(array $plantData): ?string
    {
        $modelName = trim((string) ($plantData['modelo_name'] ?? ''));
        $program = trim((string) (($plantData['modelo_programa'] ?? $plantData['programa'] ?? '')));
        $orientation = trim((string) ($plantData['orientacion'] ?? ''));

        if ($modelName === '' || $program === '' || $orientation === '') {
            return null;
        }

        return $modelName.'-'.str_replace('+', '-', $program).'-'.$orientation;
    }

    private static function buildPlantDocumentName(string $projectName, string $identifier): string
    {
        $projectPrefix = self::normalizeDocumentName($projectName.' - ');
        $normalizedIdentifier = self::normalizeDocumentName($identifier);

        if ($projectPrefix !== '' && str_starts_with($normalizedIdentifier, $projectPrefix)) {
            return trim($identifier);
        }

        return $projectName.' - '.$identifier;
    }

    private static function normalizeDocumentName(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }

    /**
     * @return list<string>
     */
    private static function resolveExcludedUpdateFields(): array
    {
        $extraSettings = SiteSetting::get('extra_settings', []);

        $configured = is_array($extraSettings)
            ? ($extraSettings['salesforce_sync_plants_excluded_fields'] ?? [])
            : [];

        if (! is_array($configured)) {
            return [];
        }

        $allowedKeys = array_keys(self::UPDATABLE_PLANT_FIELD_OPTIONS);

        return array_values(array_intersect(
            $allowedKeys,
            array_map(static fn ($value): string => trim((string) $value), $configured)
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $excludedFields
     * @return array<string, mixed>
     */
    private static function removeExcludedUpdateFields(array $data, array $excludedFields): array
    {
        if ($excludedFields === []) {
            return $data;
        }

        return collect($data)
            ->except($excludedFields)
            ->all();
    }
}
