<?php

namespace App\Filament\Resources\Asesores\Pages;

use App\Filament\Resources\Asesores\AsesorResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAsesores extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = AsesorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
