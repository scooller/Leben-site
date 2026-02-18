<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Crear una nueva transacción
     *
     * @param  array  $data  Datos de la transacción (amount, order_id, return_url, etc.)
     * @return array Respuesta con token/url de pago o datos de confirmación
     */
    public function createTransaction(array $data): array;

    /**
     * Confirmar una transacción después del retorno del usuario
     *
     * @param  string  $token  Token de la transacción
     * @return array Datos de confirmación (status, authorization_code, etc.)
     */
    public function confirmTransaction(string $token): array;

    /**
     * Obtener el estado de una transacción
     *
     * @param  string  $transactionId  ID de la transacción en la pasarela
     * @return array Estado actual de la transacción
     */
    public function getTransactionStatus(string $transactionId): array;

    /**
     * Reembolsar una transacción
     *
     * @param  string  $transactionId  ID de la transacción
     * @param  float|null  $amount  Monto a reembolsar (null = total)
     * @return array Respuesta del reembolso
     */
    public function refundTransaction(string $transactionId, ?float $amount = null): array;

    /**
     * Procesar webhook de la pasarela
     *
     * @param  array  $payload  Datos del webhook
     * @return bool Si el webhook fue procesado exitosamente
     */
    public function processWebhook(array $payload): bool;

    /**
     * Verificar si la pasarela está habilitada
     */
    public function isEnabled(): bool;

    /**
     * Obtener el nombre de la pasarela
     */
    public function getName(): string;

    /**
     * Validar configuración de la pasarela
     *
     * @return bool Si la configuración es válida
     */
    public function validateConfiguration(): bool;
}
