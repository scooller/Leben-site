<?php

namespace App\Filament\Actions;

use App\Models\Plant;
use App\Models\Proyecto;
use App\Services\Salesforce\SalesforceService;
use Exception;
use Filament\Actions\Action;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncPlantsAction
{
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
                self::execute();
            });
    }

    /**
     * Sincronizar plantas desde Salesforce a la base de datos local
     */
    public static function execute(): array
    {
        try {
            Log::info('Iniciando sincronización de plantas desde Salesforce...');

            $salesforceService = app(SalesforceService::class);
            $plants = $salesforceService->findPlants();
            $projectNamesBySalesforceId = Proyecto::query()
                ->whereNotNull('salesforce_id')
                ->pluck('name', 'salesforce_id')
                ->toArray();

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

                $hasProject = Proyecto::query()
                    ->where('salesforce_id', $plantData['proyecto_id'])
                    ->exists();

                if (! $hasProject) {
                    $skipped++;

                    continue;
                }

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
                        'orientacion' => $plantData['orientacion'],
                        'programa' => $plantData['programa'],
                        'programa2' => $plantData['programa2'],
                        'piso' => $plantData['piso'],
                        'precio_base' => $plantData['precio_base'],
                        'precio_lista' => $plantData['precio_lista'],
                        'superficie_total_principal' => $plantData['superficie_total_principal'],
                        'superficie_interior' => $plantData['superficie_interior'],
                        'superficie_util' => $plantData['superficie_util'],
                        'opportunity_id' => $plantData['opportunity_id'],
                        'superficie_terraza' => $plantData['superficie_terraza'],
                        'superficie_vendible' => $plantData['superficie_vendible'],
                        'is_active' => true,
                        'last_synced_at' => Carbon::now(),
                    ];

                    if (is_string($salesforceInteriorImageUrl) && trim($salesforceInteriorImageUrl) !== '') {
                        $updateData['salesforce_interior_image_url'] = $salesforceInteriorImageUrl;
                    }

                    $existingPlant->update($updateData);
                    $updated++;
                } else {
                    // Create con product_code
                    $createData = [
                        'salesforce_product_id' => $plantData['id'],
                        'salesforce_proyecto_id' => $plantData['proyecto_id'],
                        'name' => $plantData['name'],
                        'product_code' => $plantData['product_code'],
                        'orientacion' => $plantData['orientacion'],
                        'programa' => $plantData['programa'],
                        'programa2' => $plantData['programa2'],
                        'piso' => $plantData['piso'],
                        'precio_base' => $plantData['precio_base'],
                        'precio_lista' => $plantData['precio_lista'],
                        'superficie_total_principal' => $plantData['superficie_total_principal'],
                        'superficie_interior' => $plantData['superficie_interior'],
                        'superficie_util' => $plantData['superficie_util'],
                        'opportunity_id' => $plantData['opportunity_id'],
                        'superficie_terraza' => $plantData['superficie_terraza'],
                        'superficie_vendible' => $plantData['superficie_vendible'],
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
        } catch (Exception $e) {
            Log::error('Error al sincronizar plantas: '.$e->getMessage(), [
                'exception' => \get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al sincronizar: '.$e->getMessage(),
                'count' => 0,
            ];
        }
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
}
