<?php

namespace App\Filament\Resources\Proyectos\Tables;

use App\Models\Proyecto;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/*
colores disponibles para badge:
 red,orange,amber,yellow,lime,green,emerald,teal,cyan,sky,blue,indigo,violet,purple,fuchsia,pink,rose,
*/

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

            // Postventa,Permiso de edificación,Inicio de obra,Entrega,Construcción,Obra gruesa,Terminaciones
            TextColumn::make('etapa')
                ->label('Etapa')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'Postventa' => 'emerald',
                    'Permiso de edificación' => 'orange',
                    'Inicio de obra' => 'amber',
                    'Entrega' => 'sky',
                    'Construcción' => 'indigo',
                    'Obra gruesa' => 'rose',
                    'Terminaciones' => 'violet',
                    default => 'gray',
                })
                ->sortable()
                ->searchable(),

            TextColumn::make('comuna')
                ->label('Comuna')
                ->sortable()
                ->searchable(),

            TextColumn::make('region')
                ->label('Región')
                ->searchable(),

            TextColumn::make('asesores.full_name')
                ->label('Asesores')
                ->badge()
                ->separator(',')
                ->limitList(2)
                ->expandableLimitedList(),

            TextColumn::make('rut')
                ->label('RUT'),

            // tipo
            TextColumn::make('tipo')
                ->label('Tipo')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'best' => 'emerald',
                    'broker' => 'blue',
                    'home' => 'amber',
                    'icon' => 'cyan',
                    'invest' => 'violet',
                    default => 'gray',
                })
                ->sortable()
                ->searchable(),

            // codigo comercio
            TextColumn::make('codigo_comercio')
                ->label('Código Comercio')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            IconColumn::make('entrega_inmediata')
                ->label('Entrega Inmediata')
                ->boolean()
                ->trueIcon(Heroicon::OutlinedFire)
                ->falseIcon(Heroicon::OutlinedMoon)
                ->color(fn (bool $state): string => $state ? 'amber' : 'gray'),

            // active
            IconColumn::make('is_active')
                ->label('Activo')
                ->boolean()
                ->color(fn (bool $state): string => $state ? 'green' : 'red')
                ->sortable(),

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
                    'Postventa' => 'Postventa',
                    'Permiso de edificación' => 'Permiso de edificación',
                    'Inicio de obra' => 'Inicio de obra',
                    'Entrega' => 'Entrega',
                    'Construcción' => 'Construcción',
                    'Obra gruesa' => 'Obra gruesa',
                    'Terminaciones' => 'Terminaciones',
                ])
                ->searchable(),

            SelectFilter::make('region')
                ->label('Región')
                ->multiple()
                ->options(
                    Proyecto::query()
                        ->distinct()
                        ->whereNotNull('region')
                        ->pluck('region', 'region')
                        ->toArray()
                )
                ->searchable()
                ->preload(),

            // tipo
            SelectFilter::make('tipo')
                ->label('Tipo')
                ->multiple()
                ->options([
                    'best' => 'Best',
                    'broker' => 'Broker',
                    'home' => 'Home',
                    'icon' => 'Icon',
                    'invest' => 'Invest',
                ])
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
