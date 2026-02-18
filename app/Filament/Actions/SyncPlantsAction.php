<?php

namespace App\Filament\Actions;

use App\Models\Plant;
use App\Models\Proyecto;
use App\Services\Salesforce\SalesforceService;
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

                $plant = Plant::updateOrCreate(
                    ['salesforce_product_id' => $plantData['id']],
                    [
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
                    ]
                );

                if ($plant->wasRecentlyCreated) {
                    $synced++;
                } else {
                    $updated++;
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
                'exception' => get_class($e),
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
}
