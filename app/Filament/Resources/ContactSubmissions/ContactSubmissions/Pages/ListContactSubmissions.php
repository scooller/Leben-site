<?php

namespace App\Filament\Resources\ContactSubmissions\ContactSubmissions\Pages;

use App\Filament\Actions\ImportContactSubmissionsCsvAction;
use App\Filament\Resources\ContactSubmissions\ContactSubmissions\ContactSubmissionResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Resources\Pages\ListRecords;

class ListContactSubmissions extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = ContactSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportContactSubmissionsCsvAction::make(),
        ];
    }
}
