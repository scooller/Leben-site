<?php

namespace App\Filament\Resources\Plants\Pages;

use App\Filament\Actions\EraseAllPlantsAction;
use App\Filament\Actions\SyncPlantsAction;
use App\Filament\Resources\Plants\PlantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlants extends ListRecords
{
    protected static string $resource = PlantResource::class;

    public function getTitle(): string
    {
        return 'Plantas';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            SyncPlantsAction::make(),
            EraseAllPlantsAction::make(),
        ];
    }
}
