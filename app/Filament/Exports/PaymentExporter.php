<?php

namespace App\Filament\Exports;

use App\Models\Payment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class PaymentExporter extends Exporter
{
    protected static ?string $model = Payment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('user.name')
                ->label('Usuario'),
            ExportColumn::make('user.email')
                ->label('Email Usuario'),
            ExportColumn::make('gateway')
                ->label('Pasarela'),
            ExportColumn::make('gateway_tx_id')
                ->label('ID Transacción'),
            ExportColumn::make('amount')
                ->label('Monto'),
            ExportColumn::make('currency')
                ->label('Moneda'),
            ExportColumn::make('status')
                ->label('Estado'),
            ExportColumn::make('completed_at')
                ->label('Completado en'),
            ExportColumn::make('created_at')
                ->label('Creado en'),
            ExportColumn::make('updated_at')
                ->label('Actualizado en'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your payment export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
