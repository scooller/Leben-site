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
            ->modalDescription('¿Estás seguro de que deseas quitar todas las unidades Sale? Se desmarcarán todas las plantas actualmente asignadas como unidades Sale para que puedas seleccionarlas nuevamente.')
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
            $count = Plant::query()->where('unidad_sale', true)->count();

            Plant::query()->where('unidad_sale', true)->update([
                'unidad_sale' => false,
            ]);

            return [
                'success' => true,
                'message' => $count > 0
                    ? "Se resetearon {$count} plantas Sale correctamente"
                    : 'No había plantas asignadas como unidad Sale',
                'count' => $count,
            ];
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'message' => 'Error al resetear plantas Sale: ' . $throwable->getMessage(),
                'count' => 0,
            ];
        }
    }
}
