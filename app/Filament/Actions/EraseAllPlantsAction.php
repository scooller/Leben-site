<?php

namespace App\Filament\Actions;

use App\Models\Plant;
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
                try {
                    $count = Plant::count();
                    Plant::truncate();
                    
                    Notification::make()
                        ->title('✅ Éxito')
                        ->body("Se eliminaron {$count} plantas correctamente")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('❌ Error')
                        ->body('Error al borrar plantas: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
