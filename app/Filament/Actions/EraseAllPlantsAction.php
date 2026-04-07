<?php

namespace App\Filament\Actions;

use App\Models\Plant;
use App\Support\BusinessActivityLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class EraseAllPlantsAction
{
    /**
     * Crear la acción para borrar todas las plantas
     */
    public static function make(): Action
    {
        return Action::make('erase_all_plants')
            ->label('Borrar Todas')
            ->icon('heroicon-m-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('⚠️ Borrar todas las plantas')
            ->modalDescription('¿Estás seguro de que deseas eliminar TODAS las plantas? Esta acción no se puede deshacer.')
            ->modalSubmitActionLabel('Sí, borrar todas')
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

    public static function execute(): array
    {
        try {
            $count = Plant::count();

            Plant::query()->delete();

            BusinessActivityLogger::logMassDeletion('plants', $count);

            return [
                'success' => true,
                'message' => "Se eliminaron {$count} plantas correctamente",
                'count' => $count,
            ];
        } catch (\Throwable $throwable) {
            return [
                'success' => false,
                'message' => 'Error al borrar plantas: '.$throwable->getMessage(),
                'count' => 0,
            ];
        }
    }
}
