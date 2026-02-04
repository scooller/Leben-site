<?php

namespace App\Filament\Resources\SalesforceLeads\Pages;

use App\Filament\Resources\SalesforceLeads\SalesforceLeadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesforceLeads extends ListRecords
{
    protected static string $resource = SalesforceLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
