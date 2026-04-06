<?php

namespace App\Filament\Resources\Asesores\Pages;

use App\Filament\Resources\Asesores\AsesorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAsesor extends EditRecord
{
    protected static string $resource = AsesorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
