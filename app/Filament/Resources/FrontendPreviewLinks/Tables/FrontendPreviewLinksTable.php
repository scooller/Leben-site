<?php

namespace App\Filament\Resources\FrontendPreviewLinks\Tables;

use App\Filament\Actions\ShowQrCodeAction;
use App\Models\FrontendPreviewLink;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FrontendPreviewLinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('allowed_ip')
                    ->label('IP permitida')
                    ->placeholder('Cualquier IP')
                    ->toggleable(),

                TextColumn::make('preview_url')
                    ->label('URL Preview')
                    ->state(fn(FrontendPreviewLink $record): string => $record->previewUrl())
                    ->copyable()
                    ->wrap()
                    ->toggleable(),

                IconColumn::make('expires_at')
                    ->label('Activo')
                    ->boolean()
                    ->state(fn($record): bool => blank($record->expires_at) || $record->expires_at->isFuture()),

                TextColumn::make('expires_at')
                    ->label('Expira')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->placeholder('Sistema')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ShowQrCodeAction::make(fn(FrontendPreviewLink $record): string => $record->previewUrl()),
                DeleteAction::make()
                    ->label('Revocar')
                    ->modalHeading('Revocar link preview')
                    ->modalDescription('El enlace dejará de funcionar inmediatamente.')
                    ->successNotificationTitle('Link revocado correctamente'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->label('Revocar seleccionados')
                    ->modalHeading('Revocar links preview')
                    ->modalDescription('Los enlaces seleccionados dejarán de funcionar inmediatamente.')
                    ->successNotificationTitle('Links revocados correctamente')
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
