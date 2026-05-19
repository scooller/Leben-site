<?php

namespace App\Filament\Pages;

use AlizHarb\ActivityLog\Pages\UserActivitiesPage as BaseUserActivitiesPage;
use App\Filament\Actions\ImportContactSubmissionsCsvAction;

class UserActivitiesPage extends BaseUserActivitiesPage
{
    protected function getHeaderActions(): array
    {
        return [
            ImportContactSubmissionsCsvAction::make()
                ->label('Importar contactos CSV'),
        ];
    }
}
