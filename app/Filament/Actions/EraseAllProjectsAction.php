<?php

namespace App\Filament\Actions;

use App\Models\Proyecto;
use App\Support\BusinessActivityLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

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
            $count = Proyecto::count();

            DB::transaction(function (): void {
                Proyecto::query()->delete();
            });

            BusinessActivityLogger::logMassDeletion('proyectos', $count);

            return [
                'success' => true,
                'message' => "Se eliminaron {$count} proyectos correctamente.",
                'count' => $count,
            ];
        } catch (\Throwable $throwable) {
            return [
                'success' => false,
                'message' => 'Error al borrar proyectos: '.$throwable->getMessage(),
                'count' => 0,
            ];
        }
    }
}
