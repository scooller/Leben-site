<?php

namespace App\Filament\Resources\Proyectos\Pages;

use App\Filament\Resources\ProyectoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProyecto extends EditRecord
{
    protected static string $resource = ProyectoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
