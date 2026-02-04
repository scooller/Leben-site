<?php

namespace App\Filament\Resources\SalesforceAccounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalesforceAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('salesforce_id')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('billing_country')
                    ->searchable(),
                TextColumn::make('billing_city')
                    ->searchable(),
                TextColumn::make('industry')
                    ->searchable(),
                TextColumn::make('last_synced_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
