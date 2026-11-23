<?php

namespace App\Filament\Actions;

use App\Models\Plant;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;

class ResetSalePlantsAction
{
    /**
     * Crear la acción para resetear todas las plantas sale
     */
    public static function make(): Action
    {
        return Action::make('reset_sale_plants')
            ->label('Resetear plantas Sale')
            ->icon('heroicon-o-bookmark-slash')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('⚠️ Resetear plantas Sale')
            ->modalDescription('¿Estás seguro de que deseas quitar todas las unidades Sale y desactivar sus descuentos propios? Se desmarcarán todas las plantas actualmente asignadas como unidades Sale y se desactivarán sus descuentos propios para reiniciar la selección desde cero.')
            ->modalSubmitActionLabel('Sí, resetear')
            ->modalCancelActionLabel('Cancelar')
            ->action(function () {
                $result = self::execute();

                Notification::make()
                    ->title($result['success'] ? '✅ Éxito' : '❌ Error')
                    ->body($result['message'])
                    ->{$result['success'] ? 'success' : 'danger'}()
                    ->send();
            });
    }

    /**
     * @return array{success: bool, message: string, count: int}
     */
    public static function execute(): array
    {
        try {
            $count = Plant::query()
                ->where('unidad_sale', true)
                ->orWhere('priorizar_descuentos', true)
                ->count();

            Plant::query()
                ->where('unidad_sale', true)
                ->orWhere('priorizar_descuentos', true)
                ->update([
                    'unidad_sale' => false,
                    'priorizar_descuentos' => false,
                ]);

            return [
                'success' => true,
                'message' => $count > 0
                    ? "Se resetearon {$count} plantas Sale y sus descuentos propios correctamente"
                    : 'No había plantas asignadas como unidad Sale ni con descuentos propios',
                'count' => $count,
            ];
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'message' => 'Error al resetear plantas Sale: '.$throwable->getMessage(),
                'count' => 0,
            ];
        }
    }
}
