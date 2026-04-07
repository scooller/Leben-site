<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ManualPaymentService implements PaymentGatewayInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Crear una transacción manual
     *
     * Para pagos manuales, solo registramos la intención de pago
     * y esperamos confirmación del admin
     */
    public function createTransaction(array $data): array
    {
        Log::info('ManualPayment: Creando transacción manual', $data);

        $reference = 'MAN-'.Str::upper(Str::ulid()->toBase32());

        return [
            'reference' => $reference,
            'status' => PaymentStatus::PENDING_APPROVAL->value,
            'instructions' => filled($this->config['instructions'] ?? null)
                ? (string) $this->config['instructions']
                : null,
            'bank_accounts' => $this->config['bank_accounts'] ?? [],
            'expires_at' => $this->getExpirationDate(),
            'requires_proof' => $this->config['requires_proof'] ?? true,
        ];
    }

    /**
     * Confirmar una transacción manual
     *
     * Esta confirmación es realizada por un admin después de verificar el pago
     */
    public function confirmTransaction(string $token): array
    {
        Log::info('ManualPayment: Confirmando transacción manual', ['reference' => $token]);

        return [
            'reference' => $token,
            'status' => PaymentStatus::COMPLETED->value,
            'confirmed_at' => now()->toISOString(),
            'confirmed_by' => 'admin', // TODO: Obtener usuario actual
        ];
    }

    /**
     * Obtener estado de una transacción
     */
    public function getTransactionStatus(string $transactionId): array
    {
        Log::info('ManualPayment: Consultando estado', ['transaction_id' => $transactionId]);

        // Para pagos manuales, el estado se maneja en la base de datos
        return [
            'transaction_id' => $transactionId,
            'status' => PaymentStatus::PENDING_APPROVAL->value,
            'message' => 'Esperando verificación manual del pago',
        ];
    }

    /**
     * Reembolsar una transacción manual
     *
     * Para reembolsos manuales, solo registramos la intención
     */
    public function refundTransaction(string $transactionId, ?float $amount = null): array
    {
        Log::info('ManualPayment: Registrando reembolso manual', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
        ]);

        return [
            'success' => true,
            'refund_reference' => 'refund-manual-'.uniqid(),
            'amount' => $amount,
            'status' => 'pending_manual_processing',
            'message' => 'El reembolso debe ser procesado manualmente por el administrador',
        ];
    }

    /**
     * Procesar webhook
     *
     * Los pagos manuales no tienen webhooks
     */
    public function processWebhook(array $payload): bool
    {
        Log::warning('ManualPayment: Webhook recibido pero no soportado para pagos manuales', $payload);

        return false;
    }

    /**
     * Verificar si la pasarela está habilitada
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * Obtener el nombre de la pasarela
     */
    public function getName(): string
    {
        return $this->config['name'] ?? 'Pago Manual';
    }

    /**
     * Validar configuración
     */
    public function validateConfiguration(): bool
    {
        return isset($this->config['instructions']) || isset($this->config['bank_accounts']);
    }

    /**
     * Obtener fecha de expiración para el pago
     */
    protected function getExpirationDate(): ?string
    {
        $hours = $this->config['auto_expire_hours'] ?? null;

        if (! $hours) {
            return null;
        }

        return now()->addHours($hours)->toISOString();
    }

    /**
     * Aprobar manualmente un pago
     *
     * Método helper específico para pagos manuales
     */
    public function approvePayment(string $transactionId, array $metadata = []): array
    {
        Log::info('ManualPayment: Aprobando pago manualmente', [
            'transaction_id' => $transactionId,
            'metadata' => $metadata,
        ]);

        return [
            'transaction_id' => $transactionId,
            'status' => PaymentStatus::COMPLETED->value,
            'approved_at' => now()->toISOString(),
            'approved_by' => $metadata['approved_by'] ?? 'admin',
            'notes' => $metadata['notes'] ?? null,
        ];
    }

    /**
     * Rechazar un pago manual
     */
    public function rejectPayment(string $transactionId, string $reason = ''): array
    {
        Log::info('ManualPayment: Rechazando pago', [
            'transaction_id' => $transactionId,
            'reason' => $reason,
        ]);

        return [
            'transaction_id' => $transactionId,
            'status' => PaymentStatus::FAILED->value,
            'rejected_at' => now()->toISOString(),
            'rejection_reason' => $reason,
        ];
    }

    /**
     * Obtener instrucciones de pago
     */
    public function getPaymentInstructions(): array
    {
        return [
            'instructions' => $this->config['instructions'] ?? '',
            'bank_accounts' => $this->config['bank_accounts'] ?? [],
            'requires_proof' => $this->config['requires_proof'] ?? true,
            'auto_expire_hours' => $this->config['auto_expire_hours'] ?? null,
        ];
    }
}
