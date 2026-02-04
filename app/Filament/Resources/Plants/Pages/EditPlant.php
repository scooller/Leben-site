<?php

namespace App\Filament\Resources\Plants\Pages;

use App\Filament\Resources\Plants\PlantResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlant extends EditRecord
{
    protected static string $resource = PlantResource::class;

    public function getTitle(): string
    {
        return 'Editar Planta';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
