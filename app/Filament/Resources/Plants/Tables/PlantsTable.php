<?php

namespace App\Filament\Resources\Plants\Tables;

use App\Filament\Exports\PlantExporter;
use App\Models\Plant;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
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
		$isSaleEventActive = (bool) (SiteSetting::current()->evento_sale ?? false);

		return $table
			->defaultSort('name', 'asc')
			->columns([
				TextColumn::make('last_synced_at')
					->label('Sincronizado')
					->badge()
					->color('teal')
					->dateTime(),
				TextColumn::make('name')
					->label('Nombre')
					->searchable()
					->sortable(query: function ($query, string $direction) {
						$direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

						if ($direction === 'ASC') {
							return $query->orderByRaw('CASE WHEN (name + 0) > 0 THEN (name + 0) ELSE 9999999 END ASC, LENGTH(name) ASC, name ASC');
						}

						return $query->orderByRaw('CASE WHEN (name + 0) > 0 THEN (name + 0) ELSE 0 END DESC, LENGTH(name) DESC, name DESC');
					}),
				TextColumn::make('proyecto.name')
					->label('Proyecto')
					->badge()
					->color('indigo')
					->searchable()
					->sortable(),
				IconColumn::make('unidad_sale')
					->label('Unidad Sale')
					->boolean()
					->color(fn(bool $state): string => $state ? 'warning' : 'gray')
					->sortable(),
				// TextColumn::make('product_code')
				//     ->label('Código')
				//     ->searchable()
				//     ->sortable(),
				TextColumn::make('programa')
					->label('Programa')
					->searchable()
					->sortable()
					->toggleable(isToggledHiddenByDefault: true),
				TextColumn::make('tipo_producto')
					->label('Tipo')
					->badge()
					->color(fn(string $state): string => match ($state) {
						'DEPARTAMENTO' => 'emerald',
						'ESTACIONAMIENTO' => 'orange',
						'BODEGA' => 'amber',
						'LOCAL' => 'sky',
						default => 'gray',
					})
					->toggleable(isToggledHiddenByDefault: true)
					->searchable()
					->sortable(),
				// TextColumn::make('programa2')
				//     ->label('Programa 2')
				//     ->searchable()
				//     ->sortable(),
				TextColumn::make('piso')
					->label('Piso')
					->sortable(query: function ($query, string $direction) {
						$direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

						if ($direction === 'ASC') {
							return $query->orderByRaw('CASE WHEN (piso + 0) > 0 THEN (piso + 0) ELSE 9999999 END ASC, LENGTH(piso) ASC, piso ASC');
						}

						return $query->orderByRaw('CASE WHEN (piso + 0) > 0 THEN (piso + 0) ELSE 0 END DESC, LENGTH(piso) DESC, piso DESC');
					})
					->toggleable(isToggledHiddenByDefault: true),
				TextColumn::make('orientacion')
					->label('Orientación')
					->toggleable(isToggledHiddenByDefault: true),
				TextColumn::make('precio_base')
					->label('Precio Base')
					->badge()
					->color('indigo')
					->formatStateUsing(fn($state) => $state ? 'UF ' . number_format($state, 0, ',', '.') : '-')
					->sortable(query: function ($query, string $direction) {
						$direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
						$fallback = $direction === 'ASC' ? '999999999999' : '0';

						return $query->orderByRaw("COALESCE(precio_base, {$fallback}) {$direction}");
					}),
				TextColumn::make('precio_lista')
					->label('Precio Lista')
					->badge()
					->color('sky')
					->formatStateUsing(fn($state) => $state ? 'UF ' . number_format($state, 0, ',', '.') : '-')
					->sortable(query: function ($query, string $direction) {
						$direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
						$fallback = $direction === 'ASC' ? '999999999999' : '0';

						return $query->orderByRaw("COALESCE(precio_lista, {$fallback}) {$direction}");
					}),
				TextColumn::make('precio_final')
					->label('Precio Final')
					->badge()
					->color('emerald')
					->state(fn(Plant $record): float => $record->resolveFinalPrice($isSaleEventActive))
					->formatStateUsing(fn($state) => $state ? 'UF ' . number_format((float) $state, 0, ',', '.') : '-')
					->sortable(query: function ($query, string $direction) use ($isSaleEventActive) {
						$direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
						$fallback = $direction === 'ASC' ? '999999999999' : '0';

						$projectMaxDiscountExpression = '(SELECT p.descuento_maximo_unidad FROM proyectos p WHERE p.salesforce_id = plants.salesforce_proyecto_id LIMIT 1)';
						$projectDefaultDiscountExpression = '(SELECT p.descuento_defecto_cotizacion_web FROM proyectos p WHERE p.salesforce_id = plants.salesforce_proyecto_id LIMIT 1)';
						$projectIvaDiscountExpression = '(SELECT p.descuento_iva FROM proyectos p WHERE p.salesforce_id = plants.salesforce_proyecto_id LIMIT 1)';

						$effectiveMaxDiscount = "CASE WHEN plants.priorizar_descuentos = 1 THEN COALESCE(plants.descuento_maximo_unidad, 0) ELSE COALESCE({$projectMaxDiscountExpression}, 0) END";
						$effectiveDefaultDiscount = "CASE WHEN plants.priorizar_descuentos = 1 THEN COALESCE(plants.descuento_defecto_cotizacion_web, 0) ELSE COALESCE({$projectDefaultDiscountExpression}, 0) END";
						$effectiveIvaDiscount = "CASE WHEN plants.priorizar_descuentos = 1 THEN COALESCE(plants.descuento_iva, 0) ELSE COALESCE({$projectIvaDiscountExpression}, 0) END";

						$orderByDiscountExpression = $isSaleEventActive
							? $effectiveMaxDiscount
							: $effectiveDefaultDiscount;

						$orderByTotalDiscount = "({$orderByDiscountExpression} + {$effectiveIvaDiscount})";

						return $query->orderByRaw(
							"COALESCE(CASE WHEN {$orderByTotalDiscount} > 0 AND precio_lista > 0 THEN CASE WHEN (precio_lista - ((precio_lista * {$orderByTotalDiscount}) / 100)) < 0 THEN 0 ELSE (precio_lista - ((precio_lista * {$orderByTotalDiscount}) / 100)) END ELSE precio_base END, {$fallback}) {$direction}"
						);
					}),
				TextColumn::make('proyecto.descuento_iva')
					->label('% Dcto. IVA')
					->badge()
					->color(fn(Plant $record): string => $record->priorizar_descuentos ? 'indigo' : 'purple')
					->tooltip(fn(Plant $record): string => $record->priorizar_descuentos ? 'Priorizado desde la planta' : 'Configurado desde el proyecto')
					->state(fn(Plant $record): mixed => $record->getEffectiveDescuentoIva())
					->formatStateUsing(fn($state) => $state !== null ? number_format((float) $state, 2, ',', '.') . '%' : '-')
					->sortable(query: function ($query, string $direction) {
						$direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
						$fallback = $direction === 'ASC' ? '999999' : '-1';

						return $query->orderByRaw(
							"CASE WHEN plants.priorizar_descuentos = 1 THEN COALESCE(plants.descuento_iva, {$fallback}) ELSE COALESCE((SELECT p.descuento_iva FROM proyectos p WHERE p.salesforce_id = plants.salesforce_proyecto_id LIMIT 1), {$fallback}) END {$direction}"
						);
					}),
				TextColumn::make('proyecto.descuento_maximo_unidad')
					->label('% Máx. Unidad')
					->badge()
					->color(fn(Plant $record): string => $record->priorizar_descuentos ? 'orange' : 'amber')
					->tooltip(fn(Plant $record): string => $record->priorizar_descuentos ? 'Priorizado desde la planta' : 'Configurado desde el proyecto')
					->state(fn(Plant $record): mixed => $record->getEffectiveDescuentoMaximoUnidad())
					->formatStateUsing(fn($state) => $state !== null ? number_format((float) $state, 2, ',', '.') . '%' : '-')
					->sortable(query: function ($query, string $direction) {
						$direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
						$fallback = $direction === 'ASC' ? '999999' : '-1';

						return $query->orderByRaw(
							"CASE WHEN plants.priorizar_descuentos = 1 THEN COALESCE(plants.descuento_maximo_unidad, {$fallback}) ELSE COALESCE((SELECT p.descuento_maximo_unidad FROM proyectos p WHERE p.salesforce_id = plants.salesforce_proyecto_id LIMIT 1), {$fallback}) END {$direction}"
						);
					}),
				TextColumn::make('proyecto.descuento_defecto_cotizacion_web')
					->label('% Desc. Web')
					->badge()
					->color(fn(Plant $record): string => $record->priorizar_descuentos ? 'cyan' : 'teal')
					->tooltip(fn(Plant $record): string => $record->priorizar_descuentos ? 'Priorizado desde la planta' : 'Configurado desde el proyecto')
					->state(fn(Plant $record): mixed => $record->getEffectiveDescuentoDefectoCotizacionWeb())
					->formatStateUsing(fn($state) => $state !== null ? number_format((float) $state, 2, ',', '.') . '%' : '-')
					->sortable(query: function ($query, string $direction) {
						$direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
						$fallback = $direction === 'ASC' ? '999999' : '-1';

						return $query->orderByRaw(
							"CASE WHEN plants.priorizar_descuentos = 1 THEN COALESCE(plants.descuento_defecto_cotizacion_web, {$fallback}) ELSE COALESCE((SELECT p.descuento_defecto_cotizacion_web FROM proyectos p WHERE p.salesforce_id = plants.salesforce_proyecto_id LIMIT 1), {$fallback}) END {$direction}"
						);
					}),
				IconColumn::make('priorizar_descuentos')
					->label('Dcto. Propio')
					->boolean()
					->tooltip('Indica si esta planta tiene descuentos propios priorizados')
					->toggleable(isToggledHiddenByDefault: true)
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
					->color(fn(bool $state): string => $state ? 'green' : 'red')
					->sortable(),
				ImageColumn::make('coverImageMedia.url')
					->label('Imagen de portada')
					->circular()
					->toggleable(isToggledHiddenByDefault: true),
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
				Filter::make('solo_activos')
					->label('Solo activos')
					->toggle()
					->query(fn ($query) => $query->where('is_active', true)),
				Filter::make('solo_inactivos')
					->label('Solo inactivos')
					->toggle()
					->query(fn ($query) => $query->where('is_active', false)),
				// unidades sale
				Filter::make('solo_sale')
					->label('Solo en Sale')
					->toggle()
					->query(fn ($query) => $query->where('unidad_sale', true)),
				Filter::make('solo_no_sale')
					->label('Solo fuera de Sale')
					->toggle()
					->query(fn ($query) => $query->where('unidad_sale', false)),
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
					->label(fn(Plant $record): string => $record->is_active ? 'Desactivar' : 'Activar')
					->icon(fn(Plant $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
					->color(fn(Plant $record): string => $record->is_active ? 'warning' : 'success')
					->action(fn(Plant $record): bool => $record->update([
						'is_active' => ! $record->is_active,
					]))
					->successNotificationTitle(fn(?Plant $record = null): string => $record?->is_active ? '1 planta activada' : '1 planta desactivada'),
				// activar o desactivar unidad sale
				Action::make('toggleUnidadSale')
					->label(fn(Plant $record): string => $record->unidad_sale ? 'Desactivar en Sale' : 'Activar en Sale')
					->icon(fn(Plant $record): string => $record->unidad_sale ? 'heroicon-o-bookmark-slash' : 'heroicon-o-bookmark')
					->color(fn(Plant $record): string => $record->unidad_sale ? 'warning' : 'success')
					->action(fn(Plant $record): bool => $record->update([
						'unidad_sale' => ! $record->unidad_sale,
					]))
					->successNotificationTitle(fn(?Plant $record = null): string => $record?->unidad_sale ? '1 planta activada en Sale' : '1 planta fuera de Sale'),
				Action::make('viewInSalesforce')
					->label('Ver en Salesforce')
					->icon('heroicon-o-arrow-top-right-on-square')
					->url(
						fn(Plant $record): ?string => filled($record->salesforce_product_id)
							? "https://leben.lightning.force.com/lightning/r/Product2/{$record->salesforce_product_id}/view"
							: null,
						shouldOpenInNewTab: true
					)
					->visible(fn(Plant $record): bool => filled($record->salesforce_product_id)),
				EditAction::make(),
			])
			->toolbarActions([
				ExportAction::make()
					->label('Exportar Plantas')
					->icon('heroicon-o-document-arrow-up')
					->exporter(PlantExporter::class),
				BulkActionGroup::make([
					BulkAction::make('activateSelected')
						->label('Activar')
						->icon('heroicon-o-check-circle')
						->color('success')
						->requiresConfirmation()
						->action(function (Collection $records): void {
							$records->each->update([
								'is_active' => true,
							]);
						})
						->successNotificationTitle(fn ($records = null): string => ($count = is_countable($records) ? count($records) : null) ? "{$count} " . ($count === 1 ? 'planta activada' : 'plantas activadas') : 'Plantas activadas'),
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
						->successNotificationTitle(fn ($records = null): string => ($count = is_countable($records) ? count($records) : null) ? "{$count} " . ($count === 1 ? 'planta desactivada' : 'plantas desactivadas') : 'Plantas desactivadas'),
					// activateSelected Sale
					BulkAction::make('activateSaleSelected')
						->label('Activar en sale')
						->icon('heroicon-o-bookmark')
						->color('success')
						->requiresConfirmation()
						->action(function (Collection $records): void {
							$records->each->update([
								'unidad_sale' => true,
							]);
						})
						->successNotificationTitle(fn ($records = null): string => ($count = is_countable($records) ? count($records) : null) ? "{$count} " . ($count === 1 ? 'planta activada en Sale' : 'plantas activadas en Sale') : 'Plantas Sale'),
					BulkAction::make('deactivateSaleSelected')
						->label('Desactivar en sale')
						->icon('heroicon-o-bookmark-slash')
						->color('warning')
						->requiresConfirmation()
						->action(function (Collection $records): void {
							$records->each->update([
								'unidad_sale' => false,
							]);
						})
						->successNotificationTitle(fn ($records = null): string => ($count = is_countable($records) ? count($records) : null) ? "{$count} " . ($count === 1 ? 'planta fuera de Sale' : 'plantas fuera de Sale') : 'Plantas fuera de Sale'),
					DeleteBulkAction::make()
						->successNotificationTitle(fn ($records = null): string => ($count = is_countable($records) ? count($records) : null) ? "{$count} " . ($count === 1 ? 'planta eliminada' : 'plantas eliminadas') : 'Plantas eliminadas'),
				]),
			]);
	}
}
