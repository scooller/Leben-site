<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reservations\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\PlantReservationResource;
use App\Models\Plant;
use App\Models\PlantReservation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ListPlantReservations extends ListRecords
{
    protected static string $resource = PlantReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createManualReservation')
                ->label('Agregar reserva manual')
                ->icon('heroicon-o-plus-circle')
                ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false)
                ->authorize(fn (): bool => Auth::user()?->isAdmin() ?? false)
                ->modalHeading('Agregar unidad reservada manualmente')
                ->modalDescription('La reserva manual queda bloqueada y no se puede liberar. Solo se desbloquea eliminando este registro desde esta tabla.')
                ->modalSubmitActionLabel('Guardar reserva manual')
                ->form([
                    Select::make('plant_id')
                        ->label('Planta')
                        ->options(function (): array {
                            return Plant::query()
                                ->with(['proyecto:salesforce_id,name'])
                                ->where('is_active', true)
                                ->whereDoesntHave('reservations', function ($query): void {
                                    $query
                                        ->where('status', ReservationStatus::ACTIVE)
                                        ->where('expires_at', '>', now());
                                })
                                ->orderBy('salesforce_proyecto_id')
                                ->orderBy('name')
                                ->get(['id', 'name', 'product_code', 'salesforce_proyecto_id'])
                                ->mapWithKeys(function (Plant $plant): array {
                                    $projectName = trim((string) ($plant->proyecto?->name ?? 'Sin proyecto'));
                                    $plantName = trim((string) $plant->name);

                                    $label = "{$projectName} - {$plantName}";

                                    if (! empty($plant->product_code)) {
                                        $label .= " ({$plant->product_code})";
                                    }

                                    return [$plant->id => $label];
                                })
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('user_id')
                        ->label('Usuario asociado (opcional)')
                        ->options(function (): array {
                            return User::query()
                                ->orderBy('name')
                                ->get(['id', 'name', 'email'])
                                ->mapWithKeys(function (User $user): array {
                                    return [$user->id => "{$user->name} ({$user->email})"];
                                })
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    TextInput::make('note')
                        ->label('Motivo / nota')
                        ->maxLength(255)
                        ->placeholder('Ej: Bloqueo comercial por gestión interna')
                        ->nullable(),
                ])
                ->action(function (array $data): void {
                    $metadata = [
                        'manual_lock' => true,
                        'manual_lock_source' => 'filament_plant_reservations',
                        'manual_lock_created_by_user_id' => Auth::id(),
                        'manual_lock_created_at' => now()->toIso8601String(),
                    ];

                    if (! empty($data['note'])) {
                        $metadata['manual_lock_note'] = (string) $data['note'];
                    }

                    PlantReservation::query()->create([
                        'plant_id' => (int) $data['plant_id'],
                        'user_id' => ! empty($data['user_id']) ? (int) $data['user_id'] : null,
                        'session_token' => Str::uuid()->toString(),
                        'status' => ReservationStatus::ACTIVE,
                        'expires_at' => self::manualLockExpiresAt(),
                        'metadata' => $metadata,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Reserva manual creada')
                        ->body('La unidad quedó reservada con bloqueo manual. Solo se libera al eliminar el registro.')
                        ->send();
                }),
        ];
    }

    private static function manualLockExpiresAt(): Carbon
    {
        // plant_reservations.expires_at is TIMESTAMP in MySQL; values beyond 2038 fail.
        $candidate = now()->addYears(30);
        $timestampSafeMax = Carbon::create(2037, 12, 31, 23, 59, 59);

        return $candidate->greaterThan($timestampSafeMax) ? $timestampSafeMax : $candidate;
    }
}
