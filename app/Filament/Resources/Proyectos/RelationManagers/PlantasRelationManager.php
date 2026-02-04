<?php

namespace App\Filament\Resources\Proyectos\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PlantasRelationManager extends RelationManager
{
    protected static string $relationship = 'plantas';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product_code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('programa')
                    ->label('Programa')
                    ->sortable(),
                Tables\Columns\TextColumn::make('piso')
                    ->label('Piso')
                    ->sortable(),
                Tables\Columns\TextColumn::make('orientacion')
                    ->label('Orientación')
                    ->sortable(),
                Tables\Columns\TextColumn::make('precio_lista')
                    ->label('Precio Lista')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('precio_venta')
                    ->label('Precio Venta')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('superficie_util')
                    ->label('Superficie Útil')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activas')
                    ->native(false),
            ])
            ->recordActions([
                // Sin acciones de edición en la relación (lectura)
            ])
            ->toolbarActions([
                // Sin acciones en la barra de herramientas
            ])
            ->paginated([10, 25, 50])
            ->defaultSort('piso');
    }
}
