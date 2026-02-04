<?php

namespace App\Filament\Resources\SalesforceAccounts;

use App\Filament\Resources\SalesforceAccounts\Pages\CreateSalesforceAccount;
use App\Filament\Resources\SalesforceAccounts\Pages\EditSalesforceAccount;
use App\Filament\Resources\SalesforceAccounts\Pages\ListSalesforceAccounts;
use App\Filament\Resources\SalesforceAccounts\Schemas\SalesforceAccountForm;
use App\Filament\Resources\SalesforceAccounts\Tables\SalesforceAccountsTable;
use App\Models\SalesforceAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SalesforceAccountResource extends Resource
{
    protected static ?string $model = SalesforceAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Saleforce Account';

    public static function form(Schema $schema): Schema
    {
        return SalesforceAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesforceAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesforceAccounts::route('/'),
            'create' => CreateSalesforceAccount::route('/create'),
            'edit' => EditSalesforceAccount::route('/{record}/edit'),
        ];
    }
}
