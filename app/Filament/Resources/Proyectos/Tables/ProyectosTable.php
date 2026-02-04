<?php

namespace App\Filament\Resources\Proyectos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProyectosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::getColumns())
            ->filters(self::getFilters())
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label('Nombre')
                ->searchable()
                ->sortable(),

            TextColumn::make('etapa')
                ->label('Etapa')
                ->searchable()
                ->sortable(),

            TextColumn::make('comuna')
                ->label('Comuna')
                ->searchable(),

            TextColumn::make('region')
                ->label('Región')
                ->searchable(),

            TextColumn::make('razon_social')
                ->label('Razón Social')
                ->searchable(),

            TextColumn::make('rut')
                ->label('RUT'),

            TextColumn::make('dscto_m_x_prod_principal_porc')
                ->label('Dscto Principal (%)')
                ->numeric(decimalPlaces: 2),

            TextColumn::make('dscto_maximo_aporte_leben')
                ->label('Dscto Max Leben (%)')
                ->numeric(decimalPlaces: 2),

            TextColumn::make('tasa')
                ->label('Tasa (%)')
                ->numeric(decimalPlaces: 6),

            IconColumn::make('entrega_inmediata')
                ->label('Entrega Inmediata')
                ->boolean(),

            TextColumn::make('created_at')
                ->label('Creado')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->label('Actualizado')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public static function getFilters(): array
    {
        return [
            SelectFilter::make('etapa')
                ->label('Etapa')
                ->multiple()
                ->options([
                    'Inicio de obra' => 'Inicio de obra',
                    'En construcción' => 'En construcción',
                    'Terminado' => 'Terminado',
                    'Vendido' => 'Vendido',
                ])
                ->searchable(),

            SelectFilter::make('region')
                ->label('Región')
                ->multiple()
                ->searchable()
                ->preload(),

            SelectFilter::make('entrega_inmediata')
                ->label('Entrega Inmediata')
                ->options([
                    true => 'Sí',
                    false => 'No',
                ]),
        ];
    }
}
