<?php

namespace App\Filament\Resources\SalesforceLeads\Pages;

use App\Filament\Resources\SalesforceLeads\SalesforceLeadResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesforceLead extends EditRecord
{
    protected static string $resource = SalesforceLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
