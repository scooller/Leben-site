<?php

namespace App\Filament\Resources\Proyectos\Tables;

use App\Models\Proyecto;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

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
                Action::make('toggleActive')
                    ->label(fn (Proyecto $record): string => $record->is_active ? 'Desactivar' : 'Activar')
                    ->icon(fn (Proyecto $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Proyecto $record): string => $record->is_active ? 'warning' : 'success')
                    ->action(fn (Proyecto $record): bool => $record->update([
                        'is_active' => ! $record->is_active,
                    ]))
                    ->successNotificationTitle('Estado actualizado'),
                Action::make('viewInSalesforce')
                    ->label('Ver en Salesforce')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(
                        fn (Proyecto $record): ?string => filled($record->salesforce_id)
                            ? "https://leben.lightning.force.com/lightning/r/Proyecto__c/{$record->salesforce_id}/view"
                            : null,
                        shouldOpenInNewTab: true
                    )
                    ->visible(fn (Proyecto $record): bool => filled($record->salesforce_id)),
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
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('asesores.full_name')
                ->label('Asesores')
                ->badge()
                ->separator(',')
                ->limitList(2)
                ->expandableLimitedList(),

            TextColumn::make('rut')
                ->label('RUT')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('plantas_count')
                ->label('Plantas')
                ->counts('plantas')
                ->sortable(),

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
            TextColumn::make('transbank_commerce_code')
                ->label('Código Comercio')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: false),

            TextColumn::make('manual_payment_link')
                ->label('Enlace de Pago Manual')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            IconColumn::make('entrega_inmediata')
                ->label('Entrega Inmediata')
                ->boolean()
                ->trueIcon(Heroicon::OutlinedFire)
                ->falseIcon(Heroicon::OutlinedMoon)
                ->color(fn (bool $state): string => $state ? 'amber' : 'gray')
                ->toggleable(isToggledHiddenByDefault: false),

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
