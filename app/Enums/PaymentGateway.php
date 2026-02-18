<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case TRANSBANK = 'transbank';
    case MERCADOPAGO = 'mercadopago';
    case MANUAL = 'manual';

    /**
     * Obtener nombre legible
     */
    public function label(): string
    {
        return match ($this) {
            self::TRANSBANK => 'Transbank (Webpay)',
            self::MERCADOPAGO => 'Mercado Pago',
            self::MANUAL => 'Pago Manual',
        };
    }

    /**
     * Obtener descripción
     */
    public function description(): string
    {
        return match ($this) {
            self::TRANSBANK => 'Pago con tarjetas de débito y crédito chilenas',
            self::MERCADOPAGO => 'Pago con tarjetas y otros métodos latinoamericanos',
            self::MANUAL => 'Transferencia bancaria, efectivo u otro método offline',
        };
    }

    /**
     * Verificar si requiere procesamiento online
     */
    public function isOnline(): bool
    {
        return match ($this) {
            self::TRANSBANK, self::MERCADOPAGO => true,
            self::MANUAL => false,
        };
    }

    /**
     * Verificar si requiere aprobación manual
     */
    public function requiresManualApproval(): bool
    {
        return $this === self::MANUAL;
    }

    /**
     * Obtener array para Select de Filament
     */
    public static function toSelectArray(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $gateway) => [$gateway->value => $gateway->label()]
        )->toArray();
    }

    /**
     * Obtener ícono para UI
     */
    public function icon(): string
    {
        return match ($this) {
            self::TRANSBANK => 'heroicon-o-credit-card',
            self::MERCADOPAGO => 'heroicon-o-currency-dollar',
            self::MANUAL => 'heroicon-o-banknotes',
        };
    }
}
