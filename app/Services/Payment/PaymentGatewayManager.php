<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentGateway;
use App\Models\Payment;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /**
     * Cache de instancias de gateways
     */
    protected array $gateways = [];

    /**
     * Obtener una instancia de gateway
     */
    public function driver(?string $gateway = null): PaymentGatewayInterface
    {
        $gateway = $gateway ?? config('payments.default');

        if (! $gateway) {
            throw new InvalidArgumentException('No payment gateway specified and no default configured.');
        }

        // Retornar instancia cacheada si existe
        if (isset($this->gateways[$gateway])) {
            return $this->gateways[$gateway];
        }

        // Crear nueva instancia
        $this->gateways[$gateway] = $this->createDriver($gateway);

        return $this->gateways[$gateway];
    }

    /**
     * Crear instancia del driver
     */
    protected function createDriver(string $gateway): PaymentGatewayInterface
    {
        $config = config("payments.gateways.{$gateway}");

        if (! $config) {
            throw new InvalidArgumentException("Gateway [{$gateway}] is not configured.");
        }

        if (! ($config['enabled'] ?? false)) {
            throw new InvalidArgumentException("Gateway [{$gateway}] is disabled.");
        }

        return match ($gateway) {
            PaymentGateway::TRANSBANK->value => $this->createTransbankDriver($config),
            PaymentGateway::MERCADOPAGO->value => $this->createMercadoPagoDriver($config),
            PaymentGateway::MANUAL->value => $this->createManualDriver($config),
            default => throw new InvalidArgumentException("Gateway [{$gateway}] is not supported."),
        };
    }

    /**
     * Crear driver de Transbank
     */
    protected function createTransbankDriver(array $config): PaymentGatewayInterface
    {
        return new TransbankService($config);
    }

    /**
     * Crear driver de Mercado Pago
     */
    protected function createMercadoPagoDriver(array $config): PaymentGatewayInterface
    {
        return new MercadoPagoService($config);
    }

    /**
     * Crear driver de pago manual
     */
    protected function createManualDriver(array $config): PaymentGatewayInterface
    {
        return new ManualPaymentService($config);
    }

    /**
     * Obtener gateway desde un Payment model
     */
    public function forPayment(Payment $payment): PaymentGatewayInterface
    {
        return $this->driver($payment->gateway);
    }

    /**
     * Obtener todos los gateways disponibles
     */
    public function available(): array
    {
        return collect(config('payments.gateways'))
            ->filter(fn ($config) => $config['enabled'] ?? false)
            ->keys()
            ->toArray();
    }

    /**
     * Verificar si un gateway está disponible
     */
    public function isAvailable(string $gateway): bool
    {
        return in_array($gateway, $this->available());
    }

    /**
     * Obtener configuración de un gateway
     */
    public function config(string $gateway): ?array
    {
        return config("payments.gateways.{$gateway}");
    }

    /**
     * Magic method para llamar métodos en el driver por defecto
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
