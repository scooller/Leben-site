<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoService
{
    protected string $publicKey;
    protected string $accessToken;
    protected string $webhookSecret;
    protected string $baseUrl = 'https://api.mercadopago.com';

    public function __construct()
    {
        $this->publicKey = config('services.mercadopago.public_key', '');
        $this->accessToken = config('services.mercadopago.access_token', '');
        $this->webhookSecret = config('services.mercadopago.webhook_secret', '');
    }

    /**
     * Crear una preferencia de pago
     */
    public function createPreference(array $data): array
    {
        // Estructura esperada:
        // $data = [
        //     'items' => [
        //         ['title' => 'Producto', 'quantity' => 1, 'unit_price' => 100]
        //     ],
        //     'back_urls' => [...],
        //     'auto_return' => 'approved',
        //     'external_reference' => 'order-12345'
        // ];

        Log::info('MercadoPago: Creando preferencia de pago', $data);

        $response = Http::withToken($this->accessToken)
            ->post("{$this->baseUrl}/checkout/preferences", $data);

        if ($response->failed()) {
            Log::error('MercadoPago: Error al crear preferencia', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception('Error al crear preferencia de pago en MercadoPago');
        }

        return $response->json();
    }

    /**
     * Obtener información de un pago
     */
    public function getPayment(string $paymentId): array
    {
        $response = Http::withToken($this->accessToken)
            ->get("{$this->baseUrl}/v1/payments/{$paymentId}");

        if ($response->failed()) {
            Log::error('MercadoPago: Error al obtener pago', [
                'payment_id' => $paymentId,
                'status' => $response->status()
            ]);
            throw new \Exception('Error al obtener información del pago');
        }

        return $response->json();
    }

    /**
     * Procesar webhook de MercadoPago
     */
    public function processWebhook(array $payload, ?string $signature = null): bool
    {
        Log::info('MercadoPago: Webhook recibido', $payload);

        // Verificar firma si está configurada
        if ($this->webhookSecret && $signature) {
            if (!$this->verifyWebhookSignature($payload, $signature)) {
                Log::warning('MercadoPago: Firma de webhook inválida');
                return false;
            }
        }

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

        Log::info('MercadoPago: Procesando evento', [
            'type' => $type,
            'action' => $action
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

            Log::info('MercadoPago: Pago obtenido', [
                'id' => $paymentId,
                'status' => $payment['status'] ?? 'unknown'
            ]);

            // TODO: Actualizar estado en base de datos
            // Payment::where('gateway_tx_id', $paymentId)->update([...]);

            return true;
        } catch (\Exception $e) {
            Log::error('MercadoPago: Error procesando notificación', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Verificar firma del webhook
     */
    protected function verifyWebhookSignature(array $payload, string $signature): bool
    {
        // TODO: Implementar verificación según docs de MercadoPago
        // https://www.mercadopago.com/developers/es/docs/webhooks/additional-info/security
        return true;
    }

    /**
     * Obtener clave pública (para frontend)
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
}
