<?php

namespace App\Filament\Resources\ContactSubmissions\ContactSubmissions\Pages;

use App\Filament\Actions\ResyncSalesforceLeadAction;
use App\Filament\Resources\ContactSubmissions\ContactSubmissions\ContactSubmissionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContactSubmission extends ViewRecord
{
    protected static string $resource = ContactSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => ContactSubmissionResource::canEdit($this->record)),
            Action::make('view_salesforce')
                ->label('Ver en Salesforce')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('info')
                ->url(fn (): ?string => $this->record->salesforceLeadUrl())
                ->openUrlInNewTab()
                ->visible(fn (): bool => filled($this->record->salesforceLeadUrl())),
            ResyncSalesforceLeadAction::make(),
            DeleteAction::make()
                ->modalHeading('¿Eliminar contacto?')
                ->modalDescription('Esta acción es irreversible. Se eliminará permanentemente el envío del contacto y no podrá recuperarse.')
                ->modalSubmitActionLabel('Sí, eliminar')
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->visible(fn () => ContactSubmissionResource::canDelete($this->record)),
        ];
    }
}
