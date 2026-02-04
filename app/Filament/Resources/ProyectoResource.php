<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Proyectos\Pages\CreateProyecto;
use App\Filament\Resources\Proyectos\Pages\EditProyecto;
use App\Filament\Resources\Proyectos\Pages\ListProyectos;
use App\Filament\Resources\Proyectos\RelationManagers\PlantasRelationManager;
use App\Filament\Resources\Proyectos\Schemas\ProyectoForm;
use App\Filament\Resources\Proyectos\Tables\ProyectosTable;
use App\Models\Proyecto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProyectoResource extends Resource
{
    protected static ?string $model = Proyecto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Real Estate';

    protected static ?string $recordTitleAttribute = 'Proyectos';

    public static function form(Schema $schema): Schema
    {
        return ProyectoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProyectosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PlantasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProyectos::route('/'),
            'create' => CreateProyecto::route('/create'),
            'edit' => EditProyecto::route('/{record}/edit'),
        ];
    }
}
