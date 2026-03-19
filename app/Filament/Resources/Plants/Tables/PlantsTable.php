<?php

namespace App\Filament\Resources\Plants\Tables;

use App\Models\Plant;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/*
colores disponibles para badge:
 red,orange,amber,yellow,lime,green,emerald,teal,cyan,sky,blue,indigo,violet,purple,fuchsia,pink,rose,
*/
class PlantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('proyecto.name')
                    ->label('Proyecto')
                    ->badge()
                    ->color('indigo')
                    ->searchable()
                    ->sortable(),
                // TextColumn::make('product_code')
                //     ->label('Código')
                //     ->searchable()
                //     ->sortable(),
                TextColumn::make('programa')
                    ->label('Programa')
                    ->searchable()
                    ->sortable(),
                // TextColumn::make('programa2')
                //     ->label('Programa 2')
                //     ->searchable()
                //     ->sortable(),
                TextColumn::make('piso')
                    ->label('Piso')
                    ->sortable(),
                TextColumn::make('orientacion')
                    ->label('Orientación'),
                TextColumn::make('precio_base')
                    ->label('Precio Base')
                    ->badge()
                    ->color('indigo')
                    ->formatStateUsing(fn ($state) => $state ? 'UF '.number_format($state, 0, ',', '.') : '-')
                    ->sortable(),
                TextColumn::make('precio_lista')
                    ->label('Precio Lista')
                    ->badge()
                    ->color('sky')
                    ->formatStateUsing(fn ($state) => $state ? 'UF '.number_format($state, 0, ',', '.') : '-')
                    ->sortable(),
                // TextColumn::make('superficie_util')
                //     ->label('Sup. Útil')
                //     ->suffix(' m²')
                //     ->sortable(),
                // TextColumn::make('superficie_vendible')
                //     ->label('Sup. Vendible')
                //     ->suffix(' m²')
                //     ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->color(fn (bool $state): string => $state ? 'green' : 'red')
                    ->sortable(),
                ImageColumn::make('coverImageMedia.url')
                    ->label('Imagen de portada')
                    ->circular()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_synced_at')
                    ->label('Sincronizado')
                    ->badge()
                    ->color('teal')
                    ->dateTime(),
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
                SelectFilter::make('is_active')
                    ->label('Estado')
                    ->options([
                        1 => 'Activo',
                        0 => 'Inactivo',
                    ])
                    ->default(null),
                SelectFilter::make('proyecto')
                    ->label('Proyecto')
                    ->relationship('proyecto', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('programa')
                    ->label('Programa')
                    ->options(
                        Plant::query()
                            ->distinct()
                            ->whereNotNull('programa')
                            ->pluck('programa', 'programa')
                            ->toArray()
                    )
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('toggleActive')
                    ->label(fn (Plant $record): string => $record->is_active ? 'Desactivar' : 'Activar')
                    ->icon(fn (Plant $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Plant $record): string => $record->is_active ? 'warning' : 'success')
                    ->action(fn (Plant $record): bool => $record->update([
                        'is_active' => ! $record->is_active,
                    ]))
                    ->successNotificationTitle('Estado actualizado'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('deactivateSelected')
                        ->label('Desactivar seleccionadas')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each->update([
                                'is_active' => false,
                            ]);
                        })
                        ->successNotificationTitle('Plantas desactivadas'),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
