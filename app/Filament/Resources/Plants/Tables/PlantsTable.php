<?php

namespace App\Filament\Resources\Plants\Tables;

use App\Filament\Exports\PlantExporter;
use App\Models\Plant;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
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
                TextColumn::make('tipo_producto')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DEPARTAMENTO' => 'emerald',
                        'ESTACIONAMIENTO' => 'orange',
                        'BODEGA' => 'amber',
                        'LOCAL' => 'sky',
                        default => 'gray',
                    })
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
                TextColumn::make('porcentaje_maximo_unidad')
                    ->label('% Máx. Unidad')
                    ->badge()
                    ->color('amber')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2, ',', '.').'%' : '-')
                    ->sortable(),
                IconColumn::make('unidad_sale')
                    ->label('Unidad Sale')
                    ->boolean()
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
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
                // unidades sale
                SelectFilter::make('unidad_sale')
                    ->label('Unidad Sale')
                    ->options([
                        1 => 'Sí',
                        0 => 'No',
                    ])
                    ->default(null),
                // tipo de planta
                SelectFilter::make('tipo_producto')
                    ->label('Tipo de planta')
                    ->options([
                        'DEPARTAMENTO' => 'Departamento',
                        'ESTACIONAMIENTO' => 'Estacionamiento',
                        'BODEGA' => 'Bodega',
                        'LOCAL' => 'Local',
                    ])
                    ->default('DEPARTAMENTO'),
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
                Action::make('viewInSalesforce')
                    ->label('Ver en Salesforce')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(
                        fn (Plant $record): ?string => filled($record->salesforce_product_id)
                            ? "https://leben.lightning.force.com/lightning/r/Product2/{$record->salesforce_product_id}/view"
                            : null,
                        shouldOpenInNewTab: true
                    )
                    ->visible(fn (Plant $record): bool => filled($record->salesforce_product_id)),
                EditAction::make(),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label('Exportar Plantas')
                    ->icon('heroicon-o-document-arrow-up')
                    ->exporter(PlantExporter::class),
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
                    // activateSelected Sale
                    BulkAction::make('activateSelected')
                        ->label('Activar en sale')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each->update([
                                'unidad_sale' => true,
                            ]);
                        })
                        ->successNotificationTitle('Plantas Sale'),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
