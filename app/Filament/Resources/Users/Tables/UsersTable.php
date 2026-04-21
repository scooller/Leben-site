<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Exports\UserExporter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('user_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'marketing' => 'warning',
                        'cliente' => 'success',
                        'customer' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin' => 'Administrador',
                        'marketing' => 'Marketing',
                        'cliente' => 'Cliente',
                        'customer' => 'Cliente',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('rut')
                    ->label('RUT')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('email_verified_at')
                    ->label('Email Verificado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_type')
                    ->label('Tipo de Usuario')
                    ->options([
                        'customer' => 'Cliente',
                        'marketing' => 'Marketing',
                        'admin' => 'Administrador',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label('Exportar Usuarios')
                    ->icon('heroicon-o-document-arrow-up')
                    ->exporter(UserExporter::class),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
