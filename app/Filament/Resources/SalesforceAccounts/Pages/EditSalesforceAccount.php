<?php

namespace App\Filament\Resources\SalesforceAccounts\Pages;

use App\Filament\Resources\SalesforceAccounts\SalesforceAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesforceAccount extends EditRecord
{
    protected static string $resource = SalesforceAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
