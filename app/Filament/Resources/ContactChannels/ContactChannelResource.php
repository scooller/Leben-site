<?php

namespace App\Filament\Resources\ContactChannels;

use App\Filament\Resources\ContactChannels\Pages\CreateContactChannel;
use App\Filament\Resources\ContactChannels\Pages\EditContactChannel;
use App\Filament\Resources\ContactChannels\Pages\ListContactChannels;
use App\Filament\Resources\ContactChannels\Schemas\ContactChannelForm;
use App\Filament\Resources\ContactChannels\Tables\ContactChannelsTable;
use App\Models\ContactChannel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ContactChannelResource extends Resource
{
    protected static ?string $model = ContactChannel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Canales de Contacto';

    protected static ?string $modelLabel = 'Canal';

    protected static ?string $pluralModelLabel = 'Canales de Contacto';

    protected static string|UnitEnum|null $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return ContactChannelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactChannelsTable::configure($table);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) ContactChannel::query()->where('is_active', true)->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactChannels::route('/'),
            'create' => CreateContactChannel::route('/create'),
            'edit' => EditContactChannel::route('/{record}/edit'),
        ];
    }
}
