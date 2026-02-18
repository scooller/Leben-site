<?php

namespace App\Filament\Actions;

use App\Models\Proyecto;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class EraseAllProjectsAction
{
    public static function make(): Action
    {
        return Action::make('erase_all_proyectos')
            ->label('Borrar Todos')
            ->icon('heroicon-m-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('⚠️ Borrar todas las plantas')
            ->modalDescription('¿Estás seguro de que deseas eliminar todos los proyectos? Esta acción no se puede deshacer.')
            ->modalSubmitActionLabel('Sí, borrar todos')
            ->modalCancelActionLabel('Cancelar')
            ->action(function () {
                try {
                    $count = Proyecto::count();
                    Proyecto::truncate();

                    Notification::make()
                        ->title('✅ Éxito')
                        ->body("Se eliminaron {$count} proyectos correctamente.")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('❌ Error')
                        ->body('Error al borrar plantas: '.$e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
