<?php

namespace App\Filament\Resources\SalesforceLeads\Pages;

use App\Filament\Resources\SalesforceLeads\SalesforceLeadResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesforceLead extends CreateRecord
{
    protected static string $resource = SalesforceLeadResource::class;
}
