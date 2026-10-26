<?php

namespace App\Filament\Actions;

use App\Jobs\CreateSalesforceCaseJob;
use App\Models\ContactSubmission;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ResyncSalesforceLeadAction
{
    public static function make(): Action
    {
        return Action::make('resync_salesforce_lead')
            ->label('Resincronizar Lead en Salesforce')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Resincronizar Lead en Salesforce')
            ->modalDescription(fn (ContactSubmission $record): string => filled($record->salesforce_case_id)
                ? 'Se intentará actualizar el Lead existente en Salesforce (ID: '.$record->salesforce_case_id.').'
                : 'Se intentará crear el Lead en Salesforce nuevamente.')
            ->modalSubmitActionLabel('Resincronizar')
            ->action(function (ContactSubmission $record): void {
                $leadEnabled = (bool) config('services.salesforce.lead_enabled', config('services.salesforce.case_enabled', false));

                if (! $leadEnabled) {
                    Notification::make()
                        ->title('Salesforce deshabilitado')
                        ->body('La sincronización con Salesforce está deshabilitada en la configuración.')
                        ->warning()
                        ->send();

                    return;
                }

                $hadPriorId = filled($record->salesforce_case_id);

                // Limpiar error previo antes de reintentar
                $record->update(['salesforce_case_error' => null]);

                CreateSalesforceCaseJob::dispatchSync($record, 'manual');

                $record->refresh();

                if (filled($record->salesforce_case_id) && blank($record->salesforce_case_error)) {
                    Notification::make()
                        ->title($hadPriorId ? 'Lead actualizado' : 'Lead sincronizado')
                        ->body($hadPriorId
                            ? 'Lead actualizado en Salesforce con ID: '.$record->salesforce_case_id
                            : 'Lead creado en Salesforce con ID: '.$record->salesforce_case_id)
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Error al sincronizar')
                        ->body(filled($record->salesforce_case_error)
                            ? $record->salesforce_case_error
                            : 'No se pudo sincronizar el Lead en Salesforce.')
                        ->danger()
                        ->send();
                }
            });
    }
}
