<?php

namespace App\Filament\Resources\FrontendPreviewLinks\Tables;

use Filament\Actions\DeleteAction;
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

                IconColumn::make('expires_at')
                    ->label('Activo')
                    ->boolean()
                    ->state(fn ($record): bool => blank($record->expires_at) || $record->expires_at->isFuture()),

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
                DeleteAction::make()
                    ->label('Revocar')
                    ->modalHeading('Revocar link preview')
                    ->modalDescription('El enlace dejará de funcionar inmediatamente.')
                    ->successNotificationTitle('Link revocado correctamente'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
