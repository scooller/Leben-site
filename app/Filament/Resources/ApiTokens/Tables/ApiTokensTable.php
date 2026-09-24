<?php

namespace App\Filament\Resources\ApiTokens\Tables;

use App\Models\PersonalAccessToken;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ApiTokensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tokenable.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tokenable.email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('authorized_url')
                    ->label('URL Autorizada')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                IconColumn::make('expires_at')
                    ->label('Activo')
                    ->boolean()
                    ->state(fn ($record): bool => blank($record->expires_at) || $record->expires_at->isFuture()),

                TextColumn::make('last_used_at')
                    ->label('Último Uso')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Nunca')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Expira')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Sin expiración')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('viewKey')
                    ->label('Ver Key')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading('Confirmar identidad')
                    ->modalDescription('Ingresa tu contraseña de administrador para ver el token API.')
                    ->modalSubmitActionLabel('Revelar token')
                    ->form([
                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->required()
                            ->currentPassword()
                            ->autofocus(),
                    ])
                    ->action(function (PersonalAccessToken $record): void {
                        if (blank($record->encrypted_token)) {
                            Notification::make()
                                ->title('Token no recuperable')
                                ->body('Este token fue generado antes de la activación del respaldo seguro. Si necesitas la clave, revócalo y crea uno nuevo.')
                                ->warning()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title("Token API: {$record->name}")
                            ->body("Clave del token:\n{$record->encrypted_token}\n\nURL autorizada: {$record->authorized_url}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                DeleteAction::make()
                    ->label('Revocar')
                    ->modalHeading('Revocar token API')
                    ->modalDescription('Esta acción invalida el token y no se puede deshacer.')
                    ->successNotificationTitle('Token revocado correctamente'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
