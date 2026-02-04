<?php

namespace App\Filament\Resources\SalesforceAccounts\Pages;

use App\Filament\Resources\SalesforceAccounts\SalesforceAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesforceAccounts extends ListRecords
{
    protected static string $resource = SalesforceAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
