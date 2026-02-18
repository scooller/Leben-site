<?php

namespace App\Filament\Actions;

use App\Models\Proyecto;
use App\Services\Salesforce\SalesforceService;
use Filament\Actions\Action;
use Illuminate\Support\Carbon;

class SyncProjectsAction
{
    /**
     * Crear acción para Filament
     */
    public static function make(): Action
    {
        return Action::make('sync_proyectos')
            ->label('Sincronizar Proyectos')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->action(function () {
                self::execute();
            });
    }

    /**
     * Sincronizar proyectos desde Salesforce a la base de datos local
     */
    public static function execute(): array
    {
        try {
            $salesforceService = app(SalesforceService::class);
            $proyectos = $salesforceService->findProjects();

            if (empty($proyectos)) {
                return [
                    'success' => false,
                    'message' => 'No se encontraron proyectos en Salesforce',
                    'count' => 0,
                ];
            }

            $synced = 0;
            $updated = 0;

            foreach ($proyectos as $proyectoData) {
                $proyecto = Proyecto::updateOrCreate(
                    ['salesforce_id' => $proyectoData['id']],
                    [
                        'name' => $proyectoData['name'],
                        'descripcion' => $proyectoData['descripcion'],
                        'direccion' => $proyectoData['direccion'],
                        'comuna' => $proyectoData['comuna'],
                        'provincia' => $proyectoData['provincia'],
                        'region' => $proyectoData['region'],
                        'email' => $proyectoData['email'],
                        'telefono' => $proyectoData['telefono'],
                        'pagina_web' => $proyectoData['pagina_web'],
                        'razon_social' => $proyectoData['razon_social'],
                        'rut' => $proyectoData['rut'],
                        'fecha_inicio_ventas' => $proyectoData['fecha_inicio_ventas'],
                        'fecha_entrega' => $proyectoData['fecha_entrega'],
                        'etapa' => $proyectoData['etapa'],
                        'horario_atencion' => $proyectoData['horario_atencion'],
                        'dscto_m_x_prod_principal_porc' => $proyectoData['dscto_m_x_prod_principal_porc'],
                        'dscto_m_x_prod_principal_uf' => $proyectoData['dscto_m_x_prod_principal_uf'],
                        'dscto_m_x_bodega_porc' => $proyectoData['dscto_m_x_bodega_porc'],
                        'dscto_m_x_bodega_uf' => $proyectoData['dscto_m_x_bodega_uf'],
                        'dscto_m_x_estac_porc' => $proyectoData['dscto_m_x_estac_porc'],
                        'dscto_m_x_estac_uf' => $proyectoData['dscto_m_x_estac_uf'],
                        'dscto_max_otros_porc' => $proyectoData['dscto_max_otros_porc'],
                        'dscto_max_otros_prod_uf' => $proyectoData['dscto_max_otros_prod_uf'],
                        'dscto_maximo_aporte_leben' => $proyectoData['dscto_maximo_aporte_leben'],
                        'n_anos_1' => $proyectoData['n_anos_1'],
                        'n_anos_2' => $proyectoData['n_anos_2'],
                        'n_anos_3' => $proyectoData['n_anos_3'],
                        'n_anos_4' => $proyectoData['n_anos_4'],
                        'valor_reserva_exigido_defecto_peso' => $proyectoData['valor_reserva_exigido_defecto_peso'],
                        'valor_reserva_exigido_min_peso' => $proyectoData['valor_reserva_exigido_min_peso'],
                        'tasa' => $proyectoData['tasa'],
                        'entrega_inmediata' => $proyectoData['entrega_inmediata'],
                    ]
                );

                if ($proyecto->wasRecentlyCreated) {
                    $synced++;
                } else {
                    $updated++;
                }
            }

            return [
                'success' => true,
                'message' => "Sincronización completada. {$synced} nuevos proyectos, {$updated} actualizados",
                'count' => $synced + $updated,
                'created' => $synced,
                'updated' => $updated,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al sincronizar: '.$e->getMessage(),
                'count' => 0,
            ];
        }
    }

    /**
     * Obtener total de proyectos
     */
    public static function getTotalProjects(): int
    {
        return Proyecto::count();
    }

    /**
     * Obtener fecha del último sync
     */
    public static function getLastSyncTime(): ?Carbon
    {
        return Proyecto::latest('updated_at')->first()?->updated_at;
    }
}
