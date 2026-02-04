<?php

namespace App\Filament\Resources\Proyectos\Pages;

use App\Filament\Resources\ProyectoResource;
use App\Filament\Actions\SyncProjectsAction;
use App\Filament\Actions\EraseAllProjectsAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProyectos extends ListRecords
{
    protected static string $resource = ProyectoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            SyncProjectsAction::make(),
            EraseAllProjectsAction::make(),
        ];
    }
}
