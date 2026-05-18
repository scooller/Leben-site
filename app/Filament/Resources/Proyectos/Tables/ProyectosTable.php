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
            ->modifyQueryUsing(fn ($query) => $query->withMax('plantas as descuento_maximo_unidad_fallback', 'porcentaje_maximo_unidad'))
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

            TextColumn::make('etapa')
                ->label('Etapa')
                ->badge()
                ->formatStateUsing(fn (?string $state): ?string => Proyecto::etapaLabel($state))
                ->color(fn (?string $state): string => match (Proyecto::normalizeEtapa($state)) {
                    'postventa' => 'emerald',
                    'permiso_edificacion' => 'orange',
                    'demolicion' => 'red',
                    'inicio_obra' => 'amber',
                    'excavacion_masiva' => 'yellow',
                    'obra_gruesa' => 'rose',
                    'terminaciones' => 'violet',
                    'recepcion_municipal_y_copropiedad' => 'indigo',
                    'escrituracion' => 'blue',
                    'entrega' => 'sky',
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

            TextColumn::make('descuento_defecto_cotizacion_web')
                ->label('Desc. Defecto Web')
                ->badge()
                ->color('teal')
                ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2, ',', '.').'%' : '-')
                ->sortable(),

            TextColumn::make('descuento_maximo_unidad')
                ->label('Desc. Máx. Unidad')
                ->badge()
                ->color('amber')
                ->state(fn (Proyecto $record): ?float => $record->descuento_maximo_unidad ?? $record->descuento_maximo_unidad_fallback)
                ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2, ',', '.').'%' : '-')
                ->sortable(),

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
                ->options(Proyecto::etapaOptions())
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
