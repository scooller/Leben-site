<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reservations\Tables;

use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\PlantReservationResource;
use App\Services\PlantReservationService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PlantReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('plant.proyecto.name')
                    ->label('Proyecto')
                    ->searchable(),
                TextColumn::make('plant.name')
                    ->label('Planta')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(function (ReservationStatus|string|null $state): string {
                        $status = $state instanceof ReservationStatus ? $state : ReservationStatus::fromValue($state);

                        return $status?->color() ?? 'gray';
                    })
                    ->icon(function (ReservationStatus|string|null $state): string {
                        $status = $state instanceof ReservationStatus ? $state : ReservationStatus::fromValue($state);

                        return $status?->icon() ?? 'heroicon-o-question-mark-circle';
                    })
                    ->formatStateUsing(function (ReservationStatus|string|null $state): string {
                        $status = $state instanceof ReservationStatus ? $state : ReservationStatus::fromValue($state);

                        return $status?->label() ?? '-';
                    })
                    ->searchable(),
                TextColumn::make('lock_type')
                    ->label('Bloqueo')
                    ->state(fn ($record): string => $record->isManualLock() ? 'Manual' : 'Temporal')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Manual' ? 'danger' : 'gray')
                    ->icon(fn (string $state): string => $state === 'Manual' ? 'heroicon-o-lock-closed' : 'heroicon-o-clock'),
                TextColumn::make('expires_at')
                    ->label('Expira')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('released_by')
                    ->label('Liberada por')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['plant.proyecto', 'user']))
            ->recordUrl(fn ($record): string => PlantReservationResource::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(ReservationStatus::toSelectArray())
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('release')
                    ->label('Liberar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Liberar Reserva')
                    ->modalDescription('Esta accion liberara la reserva y permitira que otros usuarios puedan reservar esta planta.')
                    ->visible(fn ($record) => $record->status === ReservationStatus::ACTIVE && ! $record->isManualLock())
                    ->action(function ($record): void {
                        app(PlantReservationService::class)->releaseById($record->id, 'admin', 'Released from admin panel');
                    }),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->modalHeading('Eliminar reserva')
                    ->modalDescription('Esta accion eliminara definitivamente la reserva seleccionada.')
                    ->successNotificationTitle('Reserva eliminada'),
            ])
            ->toolbarActions([
                BulkAction::make('releaseSelected')
                    ->label('Liberar seleccionadas')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Liberar reservas seleccionadas')
                    ->modalDescription('Solo se liberaran las reservas que esten activas.')
                    ->action(function (Collection $records): void {
                        $service = app(PlantReservationService::class);

                        $releasedCount = $records
                            ->filter(fn ($record) => $record->status === ReservationStatus::ACTIVE && ! $record->isManualLock())
                            ->reduce(function (int $carry, $record) use ($service): int {
                                return $service->releaseById($record->id, 'admin', 'Bulk release from admin panel')
                                    ? $carry + 1
                                    : $carry;
                            }, 0);

                        $manualLockedSkipped = $records
                            ->filter(fn ($record) => $record->status === ReservationStatus::ACTIVE && $record->isManualLock())
                            ->count();

                        $body = "Se liberaron {$releasedCount} reservas activas.";
                        if ($manualLockedSkipped > 0) {
                            $body .= " {$manualLockedSkipped} reservas bloqueadas manualmente no se pueden liberar.";
                        }

                        Notification::make()
                            ->success()
                            ->title('Reservas liberadas')
                            ->body($body)
                            ->send();
                    }),
                DeleteBulkAction::make()
                    ->label('Eliminar seleccionadas')
                    ->modalHeading('Eliminar reservas seleccionadas')
                    ->modalDescription('Esta accion eliminara definitivamente las reservas seleccionadas.')
                    ->successNotificationTitle('Reservas eliminadas'),
            ]);
    }
}
