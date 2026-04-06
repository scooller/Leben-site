<?php

namespace App\Filament\Resources\Asesores\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AsesoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('Avatar')
                    ->getStateUsing(fn ($record): ?string => $record->resolved_avatar_url)
                    ->circular(),

                TextColumn::make('full_name')
                    ->label('Nombre')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name']),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('whatsapp_owner')
                    ->label('WhatsApp')
                    ->searchable(),

                TextColumn::make('salesforce_id')
                    ->label('Salesforce ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('proyectos_count')
                    ->label('Proyectos')
                    ->counts('proyectos')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Estado')
                    ->options([
                        1 => 'Activo',
                        0 => 'Inactivo',
                    ]),
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
