<?php

namespace App\Filament\Actions;

use App\Models\Plant;
use App\Services\Salesforce\SalesforceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

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
            $salesforceService = app(SalesforceService::class);
            $plants = $salesforceService->findPlants();

            if (empty($plants)) {
                return [
                    'success' => false,
                    'message' => 'No se encontraron plantas en Salesforce',
                    'count' => 0,
                ];
            }

            $synced = 0;
            $updated = 0;

            foreach ($plants as $plantData) {
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
                        'precio_venta' => $plantData['precio_venta'],
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

            return [
                'success' => true,
                'message' => "Sincronización completada. {$synced} nuevas plantas, {$updated} actualizadas",
                'count' => $synced + $updated,
                'created' => $synced,
                'updated' => $updated,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al sincronizar: ' . $e->getMessage(),
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
