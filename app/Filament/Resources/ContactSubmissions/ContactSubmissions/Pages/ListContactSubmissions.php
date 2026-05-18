<?php

namespace App\Filament\Resources\ContactSubmissions\ContactSubmissions\Pages;

use App\Filament\Actions\ImportContactSubmissionsCsvAction;
use App\Filament\Resources\ContactSubmissions\ContactSubmissions\ContactSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListContactSubmissions extends ListRecords
{
    protected static string $resource = ContactSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportContactSubmissionsCsvAction::make(),
        ];
    }
}
