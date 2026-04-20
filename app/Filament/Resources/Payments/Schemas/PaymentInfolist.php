<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Js;

class PaymentInfolist
{
    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, string>
     */
    public static function normalizeMetadataForDisplay(?array $metadata): array
    {
        if (! is_array($metadata) || $metadata === []) {
            return [];
        }

        $normalized = [];

        foreach ($metadata as $key => $value) {
            $normalized[(string) $key] = self::normalizeMetadataValue($value);
        }

        return $normalized;
    }

    private static function normalizeMetadataValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = Js::encode($value);

        return is_string($encoded) ? $encoded : '[unserializable value]';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pago')
                    ->columns(2)
                    ->components([
                        TextEntry::make('id')
                            ->label('ID'),
                        TextEntry::make('gateway')
                            ->label('Gateway')
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
                            }),
                        TextEntry::make('status')
                            ->label('Estado')
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
                            }),
                        TextEntry::make('amount')
                            ->label('Monto')
                            ->money(fn ($record): string => $record->currency ?? 'CLP'),
                        TextEntry::make('currency')
                            ->label('Moneda')
                            ->placeholder('-'),
                        TextEntry::make('gateway_tx_id')
                            ->label('Gateway TX ID')
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('completed_at')
                            ->label('Completado')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label('Creado')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Actualizado')
                            ->dateTime(),
                    ]),
                Section::make('Relaciones')
                    ->columns(2)
                    ->components([
                        TextEntry::make('user.name')
                            ->label('Usuario')
                            ->placeholder('-'),
                        TextEntry::make('project.name')
                            ->label('Proyecto')
                            ->placeholder('-'),
                        TextEntry::make('plant.name')
                            ->label('Planta')
                            ->placeholder('-'),
                    ]),
                Section::make('Facturacion')
                    ->columns(2)
                    ->components([
                        TextEntry::make('billing_name')
                            ->label('Nombre Facturacion')
                            ->placeholder('-'),
                        TextEntry::make('billing_email')
                            ->label('Email Facturacion')
                            ->placeholder('-'),
                        TextEntry::make('billing_phone')
                            ->label('Telefono Facturacion')
                            ->placeholder('-'),
                        TextEntry::make('billing_rut')
                            ->label('RUT Facturacion')
                            ->placeholder('-'),
                    ]),
                Section::make('Metadata')
                    ->components([
                        TextEntry::make('manual_payment_reference')
                            ->label('Referencia Manual')
                            ->placeholder('-')
                            ->visible(fn (Payment $record): bool => $record->requiresManualApproval())
                            ->state(fn (Payment $record): ?string => data_get($record->metadata, 'manual_payment_reference')),
                        TextEntry::make('manual_payment_expires_at')
                            ->label('Fecha limite comprobante')
                            ->placeholder('-')
                            ->dateTime()
                            ->visible(fn (Payment $record): bool => $record->requiresManualApproval())
                            ->state(fn (Payment $record): ?string => data_get($record->metadata, 'manual_payment_expires_at')),
                        TextEntry::make('manual_payment_proof_uploaded_at')
                            ->label('Comprobante subido')
                            ->placeholder('-')
                            ->dateTime()
                            ->visible(fn (Payment $record): bool => $record->requiresManualApproval())
                            ->state(fn (Payment $record): ?string => data_get($record->metadata, 'manual_payment_proof_uploaded_at')),
                        TextEntry::make('manual_payment_proof_name')
                            ->label('Nombre comprobante')
                            ->placeholder('-')
                            ->visible(fn (Payment $record): bool => $record->requiresManualApproval())
                            ->state(fn (Payment $record): ?string => data_get($record->metadata, 'manual_payment_proof_name')),
                        TextEntry::make('manual_payment_rejection_reason')
                            ->label('Motivo rechazo manual')
                            ->placeholder('-')
                            ->visible(fn (Payment $record): bool => $record->requiresManualApproval())
                            ->state(fn (Payment $record): ?string => data_get($record->metadata, 'manual_payment_rejection_reason')),
                        KeyValueEntry::make('metadata')
                            ->label('Metadata')
                            ->state(fn (Payment $record): array => self::normalizeMetadataForDisplay($record->metadata))
                            ->placeholder('Sin metadata'),
                    ]),
            ]);
    }
}
