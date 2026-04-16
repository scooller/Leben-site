<?php

namespace App\Filament\Exports;

use App\Models\ContactSubmission;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ContactSubmissionExporter extends Exporter
{
    protected static ?string $model = ContactSubmission::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name')
                ->label('Nombre'),
            ExportColumn::make('email')
                ->label('Email'),
            ExportColumn::make('phone')
                ->label('Telefono'),
            ExportColumn::make('rut')
                ->label('RUT'),
            ExportColumn::make('recipient_email')
                ->label('Destinatario'),
            ExportColumn::make('ip_address')
                ->label('IP'),
            ExportColumn::make('user_agent')
                ->label('User Agent'),
            ExportColumn::make('submitted_at')
                ->label('Enviado en'),
            ExportColumn::make('created_at')
                ->label('Creado en'),
            ExportColumn::make('updated_at')
                ->label('Actualizado en'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your contact submission export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
