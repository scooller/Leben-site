<?php

namespace App\Filament\Resources\ApiTokens\Pages;

use App\Filament\Resources\ApiTokens\ApiTokenResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListApiTokens extends ListRecords
{
    protected static string $resource = ApiTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createToken')
                ->label('Crear Token API')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->authorize(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->modalHeading('Crear token de autorización API')
                ->modalDescription('Genera un token Sanctum asociado a un usuario y restringido a una URL autorizada.')
                ->modalSubmitActionLabel('Crear token')
                ->form([
                    Select::make('tokenable_id')
                        ->label('Usuario')
                        ->options(
                            User::query()
                                ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
                                ->orderBy('name')
                                ->pluck('email', 'id')
                                ->all()
                        )
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('name')
                        ->label('Nombre del token')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('integracion-frontend'),
                    TextInput::make('authorized_url')
                        ->label('URL autorizada')
                        ->url()
                        ->required()
                        ->helperText('URL externa del cliente que consumirá la API (ej: https://miapp.cliente.com). Se valida contra Origin/Referer o X-Authorized-Url. API base: '.url('/api/v1')),
                    DateTimePicker::make('expires_at')
                        ->label('Expira en')
                        ->seconds(false)
                        ->helperText('Opcional. Si no defines fecha, el token no expira.')
                        ->nullable(),
                ])
                ->action(function (array $data): void {
                    $user = User::query()
                        ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
                        ->findOrFail($data['tokenable_id']);

                    $newToken = $user->createToken(
                        $data['name'],
                        ['*'],
                        filled($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
                    );

                    $newToken->accessToken->forceFill([
                        'authorized_url' => rtrim((string) $data['authorized_url'], '/'),
                        'encrypted_token' => $newToken->plainTextToken,
                    ])->save();

                    Notification::make()
                        ->title('Token API creado')
                        ->body("Token generado:\n{$newToken->plainTextToken}\n\nAPI Base: ".url('/api/v1')."\nURL autorizada: ".rtrim((string) $data['authorized_url'], '/'))
                        ->persistent()
                        ->success()
                        ->send();
                }),
        ];
    }
}
