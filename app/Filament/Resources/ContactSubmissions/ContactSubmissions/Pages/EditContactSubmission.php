<?php

namespace App\Filament\Resources\ContactSubmissions\ContactSubmissions\Pages;

use App\Filament\Actions\ResyncSalesforceLeadAction;
use App\Filament\Resources\ContactSubmissions\ContactSubmissions\ContactSubmissionResource;
use App\Jobs\CreateSalesforceCaseJob;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContactSubmission extends EditRecord
{
    protected static string $resource = ContactSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ResyncSalesforceLeadAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $leadEnabled = (bool) config('services.salesforce.lead_enabled', config('services.salesforce.case_enabled', false));

        if ($leadEnabled && filled($this->record->salesforce_case_id)) {
            CreateSalesforceCaseJob::dispatchSync($this->record, 'manual');
            $this->record->refresh();

            if (filled($this->record->salesforce_case_error)) {
                Notification::make()
                    ->title('Guardado localmente, error en Salesforce')
                    ->body($this->record->salesforce_case_error)
                    ->warning()
                    ->send();
            } else {
                Notification::make()
                    ->title('Lead actualizado en Salesforce')
                    ->body('Los cambios del contacto fueron sincronizados con Salesforce.')
                    ->success()
                    ->send();
            }
        }
    }
}
