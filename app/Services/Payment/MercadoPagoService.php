<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoService implements PaymentGatewayInterface
{
    protected array $config;

    protected string $publicKey;

    protected string $accessToken;

    protected string $webhookSecret;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->publicKey = $config['public_key'] ?? '';
        $this->accessToken = $config['access_token'] ?? '';
        $this->webhookSecret = $config['webhook_secret'] ?? '';

        $this->configureSDK();
    }

    /**
     * Configurar el SDK de Mercado Pago
     */
    protected function configureSDK(): void
    {
        MercadoPagoConfig::setAccessToken($this->accessToken);
        // MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL); // Para testing
    }

    /**
     * Crear una transacción (preferencia de pago)
     *
     * @param  array  $data  Datos de la transacción
     *                       - amount: Monto de la transacción
     *                       - description: Descripción del pago
     *                       - external_reference: Referencia externa (ej: ID de orden)
     *                       - payer_email: Email del pagador
     */
    public function createTransaction(array $data): array
    {
        try {
            $client = new PreferenceClient;

            $preference = [
                'items' => [
                    [
                        'title' => $data['description'] ?? 'Pago',
                        'quantity' => 1,
                        'unit_price' => (float) $data['amount'],
                        'currency_id' => $data['currency'] ?? 'CLP',
                    ],
                ],
                'back_urls' => [
                    'success' => $this->config['success_url'] ?? '',
                    'failure' => $this->config['failure_url'] ?? '',
                    'pending' => $this->config['pending_url'] ?? '',
                ],
                'auto_return' => 'approved',
                'external_reference' => $data['external_reference'] ?? null,
                'statement_descriptor' => $data['statement_descriptor'] ?? null,
            ];

            // Agregar información del pagador si está disponible
            if (isset($data['payer_email'])) {
                $preference['payer'] = [
                    'email' => $data['payer_email'],
                ];

                if (isset($data['payer_name'])) {
                    $preference['payer']['name'] = $data['payer_name'];
                }
            }

            Log::debug('MercadoPago: Creando preferencia de pago', $preference);

            $response = $client->create($preference);

            Log::debug('MercadoPago: Preferencia creada exitosamente', [
                'id' => $response->id,
                'init_point' => $response->init_point,
            ]);

            return [
                'preference_id' => $response->id,
                'init_point' => $response->init_point,
                'sandbox_init_point' => $response->sandbox_init_point ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('MercadoPago: Error al crear preferencia', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw new \Exception('Error al crear preferencia de pago en MercadoPago: '.$e->getMessage());
        }
    }

    /**
     * Confirmar una transacción
     *
     * @param  string  $token  Payment ID de Mercado Pago
     */
    public function confirmTransaction(string $token): array
    {
        return $this->getPayment($token);
    }

    /**
     * Obtener información de un pago
     *
     * @param  string  $transactionId  Payment ID
     */
    public function getTransactionStatus(string $transactionId): array
    {
        return $this->getPayment($transactionId);
    }

    /**
     * Obtener detalles de un pago usando el SDK
     *
     * @param  string  $paymentId  Payment ID
     */
    protected function getPayment(string $paymentId): array
    {
        try {
            $client = new PaymentClient;
            $payment = $client->get((int) $paymentId);

            Log::debug('MercadoPago: Pago obtenido', [
                'id' => $payment->id,
                'status' => $payment->status,
            ]);

            return [
                'id' => $payment->id,
                'status' => $payment->status,
                'status_detail' => $payment->status_detail,
                'transaction_amount' => $payment->transaction_amount,
                'currency_id' => $payment->currency_id,
                'payment_method_id' => $payment->payment_method_id,
                'payment_type_id' => $payment->payment_type_id,
                'date_created' => $payment->date_created,
                'date_approved' => $payment->date_approved,
                'external_reference' => $payment->external_reference,
                'description' => $payment->description,
                'payer' => [
                    'id' => $payment->payer?->id,
                    'email' => $payment->payer?->email,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('MercadoPago: Error al obtener pago', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Error al obtener información del pago: '.$e->getMessage());
        }
    }

    /**
     * Reembolsar una transacción
     *
     * @param  string  $transactionId  Payment ID
     * @param  float|null  $amount  Monto a reembolsar (null = total)
     */
    public function refundTransaction(string $transactionId, ?float $amount = null): array
    {
        try {
            Log::debug('MercadoPago: Procesando reembolso', [
                'payment_id' => $transactionId,
                'amount' => $amount,
            ]);

            // Usar API REST directamente para reembolsos
            $url = "https://api.mercadopago.com/v1/payments/{$transactionId}/refunds";

            /** @var \Illuminate\Http\Client\Response $response */
            $response = \Illuminate\Support\Facades\Http::withToken($this->accessToken)
                ->post($url, $amount !== null ? ['amount' => $amount] : []);

            if ($response->failed()) {
                throw new \Exception('Error en API de MercadoPago: '.$response->body());
            }

            $refund = $response->json();

            Log::debug('MercadoPago: Reembolso procesado', [
                'refund_id' => $refund['id'] ?? null,
                'status' => $refund['status'] ?? null,
            ]);

            return [
                'refund_id' => $refund['id'] ?? null,
                'status' => $refund['status'] ?? null,
                'amount' => $refund['amount'] ?? $amount,
                'payment_id' => $refund['payment_id'] ?? $transactionId,
                'date_created' => $refund['date_created'] ?? now()->toISOString(),
            ];
        } catch (\Exception $e) {
            Log::error('MercadoPago: Error al reembolsar', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Error al procesar reembolso: '.$e->getMessage());
        }
    }

    /**
     * Procesar webhook de MercadoPago
     */
    public function processWebhook(array $payload): bool
    {
        Log::debug('MercadoPago: Webhook recibido', $payload);

        // Procesar según el tipo de evento
        if (isset($payload['type'])) {
            return $this->handleWebhookEvent($payload);
        }

        return false;
    }

    /**
     * Manejar eventos del webhook
     */
    protected function handleWebhookEvent(array $payload): bool
    {
        $type = $payload['type'] ?? null;
        $action = $payload['action'] ?? null;

        Log::debug('MercadoPago: Procesando evento', [
            'type' => $type,
            'action' => $action,
        ]);

        switch ($type) {
            case 'payment':
                if (isset($payload['data']['id'])) {
                    return $this->processPaymentNotification($payload['data']['id']);
                }
                break;

            case 'merchant_order':
                // Procesar orden de merchant
                break;
        }

        return false;
    }

    /**
     * Procesar notificación de pago
     */
    protected function processPaymentNotification(string $paymentId): bool
    {
        try {
            $payment = $this->getPayment($paymentId);

            Log::debug('MercadoPago: Pago obtenido', [
                'id' => $paymentId,
                'status' => $payment['status'] ?? 'unknown',
            ]);

            // TODO: Actualizar estado en base de datos
            // Payment::where('gateway_tx_id', $paymentId)->update([...]);

            return true;
        } catch (\Exception $e) {
            Log::error('MercadoPago: Error procesando notificación', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verificar firma del webhook
     *
     * Valida la firma del webhook según la documentación de MercadoPago:
     * https://www.mercadopago.com/developers/es/docs/webhooks/additional-info/security
     *
     * @param  string  $xSignatureHeader  Header x-signature de la solicitud (format: ts={timestamp},v1={signature})
     * @param  string  $rawBody  Body raw de la solicitud (JSON sin parsear)
     * @return bool True si la firma es válida, false en caso contrario
     */
    public function verifyWebhookSignature(string $xSignatureHeader, string $rawBody): bool
    {
        if (empty($this->webhookSecret) || empty($xSignatureHeader)) {
            Log::warning('MercadoPago: Webhook signature verification skipped - missing secret or header');

            return false;
        }

        try {
            // Parsear el header x-signature (formato: ts={timestamp},v1={signature})
            $signatureParts = [];
            foreach (explode(',', $xSignatureHeader) as $part) {
                $kv = explode('=', trim($part), 2);
                if (count($kv) === 2) {
                    $signatureParts[$kv[0]] = $kv[1];
                }
            }

            $timestamp = $signatureParts['ts'] ?? null;
            $providedSignature = $signatureParts['v1'] ?? null;

            if (! $timestamp || ! $providedSignature) {
                Log::warning('MercadoPago: Invalid signature header format', [
                    'header' => $xSignatureHeader,
                ]);

                return false;
            }

            // Verificar que el timestamp no sea antiguo (máximo 10 minutos)
            $currentTime = time();
            $timeDiff = abs($currentTime - (int) $timestamp);

            if ($timeDiff > 600) { // 10 minutos = 600 segundos
                Log::warning('MercadoPago: Webhook timestamp too old', [
                    'timestamp' => $timestamp,
                    'current_time' => $currentTime,
                    'diff_seconds' => $timeDiff,
                ]);

                return false;
            }

            // Construir el string a verificar: {timestamp}.{body}
            $dataToVerify = "{$timestamp}.{$rawBody}";

            // Calcular HMAC-SHA256
            $calculatedSignature = hash_hmac('sha256', $dataToVerify, $this->webhookSecret);

            // Comparar firmas (usar comparison seguro contra timing attacks)
            $isValid = hash_equals($calculatedSignature, $providedSignature);

            if (! $isValid) {
                Log::warning('MercadoPago: Invalid webhook signature', [
                    'expected' => substr($calculatedSignature, 0, 10).'...',
                    'provided' => substr($providedSignature, 0, 10).'...',
                ]);
            }

            return $isValid;
        } catch (\Exception $e) {
            Log::error('MercadoPago: Error verifying webhook signature', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Obtener clave pública (para frontend)
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
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
        return 'Mercado Pago';
    }

    /**
     * Validar configuración de la pasarela
     */
    public function validateConfiguration(): bool
    {
        return ! empty($this->publicKey) && ! empty($this->accessToken);
    }
}
