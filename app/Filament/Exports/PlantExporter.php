<?php

namespace App\Filament\Exports;

use App\Models\Plant;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class PlantExporter extends Exporter
{
    protected static ?string $model = Plant::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name')
                ->label('Nombre'),
            ExportColumn::make('proyecto.name')
                ->label('Proyecto'),
            ExportColumn::make('product_code')
                ->label('Código'),
            ExportColumn::make('programa')
                ->label('Programa'),
            ExportColumn::make('programa2')
                ->label('Programa 2'),
            ExportColumn::make('piso')
                ->label('Piso'),
            ExportColumn::make('orientacion')
                ->label('Orientación'),
            ExportColumn::make('precio_base')
                ->label('Precio Base'),
            ExportColumn::make('precio_lista')
                ->label('Precio Lista'),
            ExportColumn::make('porcentaje_maximo_unidad')
                ->label('Porcentaje Máximo de Unidad'),
            ExportColumn::make('priorizar_descuentos')
                ->label('Priorizar Descuentos'),
            ExportColumn::make('descuento_defecto_cotizacion_web')
                ->label('Descuento Web'),
            ExportColumn::make('descuento_maximo_unidad')
                ->label('Descuento Máximo Unidad'),
            ExportColumn::make('descuento_iva')
                ->label('Descuento IVA'),
            ExportColumn::make('superficie_total_principal')
                ->label('Sup. Total Principal'),
            ExportColumn::make('superficie_interior')
                ->label('Sup. Interior'),
            ExportColumn::make('superficie_util')
                ->label('Sup. Útil'),
            ExportColumn::make('superficie_terraza')
                ->label('Sup. Terraza'),
            ExportColumn::make('is_active')
                ->label('Activo'),
            ExportColumn::make('last_synced_at')
                ->label('Sincronizado en'),
            ExportColumn::make('created_at')
                ->label('Creado en'),
            ExportColumn::make('updated_at')
                ->label('Actualizado en'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your plant export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
