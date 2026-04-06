<?php

namespace App\Filament\Resources\Asesores;

use App\Filament\Resources\Asesores\Pages\CreateAsesor;
use App\Filament\Resources\Asesores\Pages\EditAsesor;
use App\Filament\Resources\Asesores\Pages\ListAsesores;
use App\Filament\Resources\Asesores\RelationManagers\ProyectosRelationManager;
use App\Filament\Resources\Asesores\Schemas\AsesorForm;
use App\Filament\Resources\Asesores\Tables\AsesoresTable;
use App\Models\Asesor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use UnitEnum;

class AsesorResource extends Resource
{
    protected static ?string $model = Asesor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Real Estate';

    protected static ?string $navigationLabel = 'Asesores';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getNavigationBadge(): ?string
    {
        if (! SchemaFacade::hasTable((new (static::getModel()))->getTable())) {
            return null;
        }

        return (string) static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'blue';
    }

    public static function form(Schema $schema): Schema
    {
        return AsesorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AsesoresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProyectosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAsesores::route('/'),
            'create' => CreateAsesor::route('/create'),
            'edit' => EditAsesor::route('/{record}/edit'),
        ];
    }
}
