<?php

namespace App\Filament\Resources\FrontendPreviewLinks;

use App\Filament\Resources\FrontendPreviewLinks\Pages\ListFrontendPreviewLinks;
use App\Filament\Resources\FrontendPreviewLinks\Tables\FrontendPreviewLinksTable;
use App\Models\FrontendPreviewLink;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class FrontendPreviewLinkResource extends Resource
{
    protected static ?string $model = FrontendPreviewLink::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Links Preview';

    protected static ?string $modelLabel = 'Link Preview';

    protected static ?string $pluralModelLabel = 'Links Preview';

    protected static string|UnitEnum|null $navigationGroup = 'Herramientas';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::userCanManagePreviewLinks();
    }

    public static function canViewAny(): bool
    {
        return static::userCanManagePreviewLinks();
    }

    public static function canCreate(): bool
    {
        return static::userCanManagePreviewLinks();
    }

    public static function canDelete($record): bool
    {
        return static::userCanManagePreviewLinks();
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) FrontendPreviewLink::query()->active()->count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return FrontendPreviewLinksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFrontendPreviewLinks::route('/'),
        ];
    }

    private static function userCanManagePreviewLinks(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }
}
