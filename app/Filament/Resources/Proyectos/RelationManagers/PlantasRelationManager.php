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
                    ->sortable(query: function ($query, string $direction) {
                        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

                        if ($direction === 'ASC') {
                            return $query->orderByRaw('CASE WHEN (name + 0) > 0 THEN (name + 0) ELSE 9999999 END ASC, LENGTH(name) ASC, name ASC');
                        }

                        return $query->orderByRaw('CASE WHEN (name + 0) > 0 THEN (name + 0) ELSE 0 END DESC, LENGTH(name) DESC, name DESC');
                    }),
                Tables\Columns\TextColumn::make('product_code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('programa')
                    ->label('Programa')
                    ->sortable(),
                Tables\Columns\TextColumn::make('piso')
                    ->label('Piso')
                    ->sortable(query: function ($query, string $direction) {
                        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

                        if ($direction === 'ASC') {
                            return $query->orderByRaw('CASE WHEN (piso + 0) > 0 THEN (piso + 0) ELSE 9999999 END ASC, LENGTH(piso) ASC, piso ASC');
                        }

                        return $query->orderByRaw('CASE WHEN (piso + 0) > 0 THEN (piso + 0) ELSE 0 END DESC, LENGTH(piso) DESC, piso DESC');
                    }),
                Tables\Columns\TextColumn::make('orientacion')
                    ->label('Orientación')
                    ->sortable(),
                Tables\Columns\TextColumn::make('precio_lista')
                    ->label('Precio Lista')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.'))
                    ->sortable(query: function ($query, string $direction) {
                        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
                        $fallback = $direction === 'ASC' ? '999999999999' : '0';

                        return $query->orderByRaw("COALESCE(precio_lista, {$fallback}) {$direction}");
                    }),
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
