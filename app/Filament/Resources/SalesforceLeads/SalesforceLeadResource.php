<?php

namespace App\Filament\Resources\SalesforceLeads;

use App\Filament\Resources\SalesforceLeads\Pages\CreateSalesforceLead;
use App\Filament\Resources\SalesforceLeads\Pages\EditSalesforceLead;
use App\Filament\Resources\SalesforceLeads\Pages\ListSalesforceLeads;
use App\Filament\Resources\SalesforceLeads\Schemas\SalesforceLeadForm;
use App\Filament\Resources\SalesforceLeads\Tables\SalesforceLeadsTable;
use App\Models\SalesforceLead;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SalesforceLeadResource extends Resource
{
    protected static ?string $model = SalesforceLead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Saleforce Lead';

    public static function form(Schema $schema): Schema
    {
        return SalesforceLeadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesforceLeadsTable::configure($table);
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
            'index' => ListSalesforceLeads::route('/'),
            'create' => CreateSalesforceLead::route('/create'),
            'edit' => EditSalesforceLead::route('/{record}/edit'),
        ];
    }
}
