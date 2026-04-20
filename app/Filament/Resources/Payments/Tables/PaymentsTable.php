<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Filament\Exports\PaymentExporter;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Payments\Support\ManualPaymentActionSupport;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable(),
                TextColumn::make('project.name')
                    ->label('Proyecto')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('plant.name')
                    ->label('Planta')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('gateway')
                    ->badge()
                    ->color('info')
                    ->icon(function (PaymentGateway|string|null $state): string {
                        $gateway = $state instanceof PaymentGateway
                            ? $state
                            : PaymentGateway::fromValue((string) $state);

                        return $gateway?->icon() ?? 'heroicon-o-credit-card';
                    })
                    ->formatStateUsing(function (PaymentGateway|string|null $state): string {
                        $gateway = $state instanceof PaymentGateway
                            ? $state
                            : PaymentGateway::fromValue((string) $state);

                        return $gateway?->label() ?? '-';
                    })
                    ->searchable(),
                TextColumn::make('gateway_tx_id')
                    ->searchable(),
                TextColumn::make('billing_email')
                    ->label('Email Facturacion')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('billing_rut')
                    ->label('RUT Facturacion')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(function (PaymentStatus|string|null $state): string {
                        $status = $state instanceof PaymentStatus
                            ? $state
                            : PaymentStatus::fromValue((string) $state);

                        return $status?->color() ?? 'gray';
                    })
                    ->icon(function (PaymentStatus|string|null $state): string {
                        $status = $state instanceof PaymentStatus
                            ? $state
                            : PaymentStatus::fromValue((string) $state);

                        return $status?->icon() ?? 'heroicon-o-question-mark-circle';
                    })
                    ->formatStateUsing(function (PaymentStatus|string|null $state): string {
                        $status = $state instanceof PaymentStatus
                            ? $state
                            : PaymentStatus::fromValue((string) $state);

                        return $status?->label() ?? '-';
                    })
                    ->searchable(),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(fn ($record): string => PaymentResource::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('gateway')
                    ->label('Gateway')
                    ->options(PaymentGateway::toSelectArray())
                    ->searchable(),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(PaymentStatus::toSelectArray())
                    ->searchable(),
                SelectFilter::make('project_id')
                    ->label('Proyecto')
                    ->relationship('project', 'name')
                    ->searchable(),
                SelectFilter::make('plant_id')
                    ->label('Planta')
                    ->relationship('plant', 'name')
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('downloadManualProof')
                    ->label('Descargar Comprobante')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (Payment $record): bool => ManualPaymentActionSupport::hasManualProof($record))
                    ->action(function (Payment $record) {
                        $path = ManualPaymentActionSupport::manualProofPath($record);

                        if (! $path) {
                            Notification::make()
                                ->danger()
                                ->title('No hay comprobante asociado.')
                                ->send();

                            return null;
                        }

                        return Storage::download($path, ManualPaymentActionSupport::manualProofName($record));
                    }),
                Action::make('approveManualPayment')
                    ->label('Aprobar Pago Manual')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record): bool => ManualPaymentActionSupport::isManualPendingApproval($record))
                    ->action(function (Payment $record): void {
                        $approved = ManualPaymentActionSupport::approve($record, Auth::id());

                        Notification::make()
                            ->title($approved ? 'Pago manual aprobado.' : 'No se pudo aprobar el pago manual.')
                            ->{$approved ? 'success' : 'danger'}()
                            ->send();
                    }),
                Action::make('rejectManualPayment')
                    ->label('Rechazar Pago Manual')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')
                            ->label('Motivo de rechazo')
                            ->maxLength(500)
                            ->required(),
                    ])
                    ->visible(fn (Payment $record): bool => ManualPaymentActionSupport::isManualPendingApproval($record))
                    ->action(function (Payment $record, array $data): void {
                        $rejected = ManualPaymentActionSupport::reject(
                            payment: $record,
                            reason: (string) ($data['reason'] ?? 'Pago manual rechazado por administracion'),
                            rejectedBy: Auth::id(),
                        );

                        Notification::make()
                            ->title($rejected ? 'Pago manual rechazado.' : 'No se pudo rechazar el pago manual.')
                            ->{$rejected ? 'success' : 'danger'}()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label('Exportar Pagos')
                    ->icon('heroicon-o-document-arrow-up')
                    ->exporter(PaymentExporter::class),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
