<?php

namespace App\Filament\Resources\FrontendPreviewLinks\Pages;

use App\Filament\Resources\FrontendPreviewLinks\FrontendPreviewLinkResource;
use App\Models\FrontendPreviewLink;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ListFrontendPreviewLinks extends ListRecords
{
    protected static string $resource = FrontendPreviewLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createPreviewLink')
                ->label('Crear Link Preview')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => $this->userCanManagePreviewLinks())
                ->authorize(fn (): bool => $this->userCanManagePreviewLinks())
                ->modalHeading('Crear link temporal para revisión')
                ->modalDescription('Genera un enlace temporal que habilita mostrar plantas aunque esté desactivado en configuración.')
                ->modalSubmitActionLabel('Crear link')
                ->form([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('revision-pre-entrega'),
                    DateTimePicker::make('expires_at')
                        ->label('Expira en')
                        ->required()
                        ->seconds(false)
                        ->default(now()->addDay())
                        ->helperText('Fecha límite de uso del enlace.'),
                    TextInput::make('allowed_ip')
                        ->label('IP permitida (opcional)')
                        ->maxLength(255)
                        ->placeholder('181.44.10.20 o 181.44.10.20, 190.20.0.0/16')
                        ->helperText('Puedes indicar 1 o varias IP/CIDR separadas por coma. Si queda vacío, permite cualquier IP.'),
                    TextInput::make('preview_path')
                        ->label('Ruta inicial')
                        ->default('/')
                        ->maxLength(255)
                        ->helperText('Ruta del frontend para abrir el preview. Ejemplo: / o /plantas.'),
                ])
                ->action(function (array $data): void {
                    $plainToken = Str::random(64);

                    FrontendPreviewLink::query()->create([
                        'name' => $data['name'],
                        'token' => $plainToken,
                        'allowed_ip' => filled($data['allowed_ip'] ?? null) ? trim((string) $data['allowed_ip']) : null,
                        'expires_at' => Carbon::parse($data['expires_at']),
                        'created_by' => Auth::id(),
                    ]);

                    $baseUrl = rtrim((string) (SiteSetting::current()->site_url ?: config('app.url')), '/');
                    $previewPath = trim((string) ($data['preview_path'] ?? '/'));
                    $previewPath = $previewPath === '' ? '/' : $previewPath;

                    if (! str_starts_with($previewPath, '/')) {
                        $previewPath = '/'.$previewPath;
                    }

                    $separator = str_contains($previewPath, '?') ? '&' : '?';
                    $previewUrl = $baseUrl.$previewPath.$separator.'preview_token='.$plainToken;

                    Notification::make()
                        ->title('Link preview creado')
                        ->body("Comparte esta URL temporal ahora, no volverá a mostrarse:\n{$previewUrl}")
                        ->persistent()
                        ->success()
                        ->send();
                }),
        ];
    }

    private function userCanManagePreviewLinks(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }
}
