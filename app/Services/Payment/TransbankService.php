<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;
use Transbank\Webpay\WebpayPlus;
use Transbank\Webpay\WebpayPlus\Transaction;

class TransbankService implements PaymentGatewayInterface
{
    protected array $config;

    protected string $environment;

    protected string $commerceCode;

    protected string $apiKey;

    protected string $returnUrl;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->environment = $config['environment'] ?? 'integration';
        $this->commerceCode = $config['commerce_code'] ?? '';
        $this->apiKey = $config['api_key'] ?? '';
        $this->returnUrl = $config['return_url'] ?? '';

        $this->configureSDK();
    }

    /**
     * Configurar el SDK de Transbank
     */
    protected function configureSDK(): void
    {
        // La configuración se hace en cada método (create/commit)
        // porque el SDK de Transbank v5 usa configuración estática global
    }

    /**
     * Crear una transacción Webpay Plus
     *
     * @param  array  $data  Datos de la transacción
     *                       - amount: Monto de la transacción
     *                       - buy_order: Orden de compra (máx 26 caracteres)
     *                       - session_id: ID de sesión (máx 61 caracteres)
     *                       - return_url: URL de retorno (opcional, usa default si no se provee)
     */
    public function createTransaction(array $data): array
    {
        try {
            $amount = $data['amount'];
            $buyOrder = $data['buy_order'] ?? 'ORDER-'.time();
            $sessionId = $data['session_id'] ?? uniqid('session-');
            $returnUrl = $data['return_url'] ?? $this->returnUrl;

            Log::info('Transbank: Creando transacción', [
                'amount' => $amount,
                'buy_order' => $buyOrder,
                'session_id' => $sessionId,
                'environment' => $this->environment,
            ]);

            // Obtener credenciales (usar defaults de integración si están vacías)
            $commerceCode = $this->commerceCode ?: WebpayPlus::INTEGRATION_COMMERCE_CODE;
            $apiKey = $this->apiKey ?: WebpayPlus::INTEGRATION_API_KEY;

            // Crear instancia de Transaction configurada para el ambiente
            if ($this->environment === 'production') {
                $transaction = Transaction::buildForProduction($commerceCode, $apiKey);
            } else {
                $transaction = Transaction::buildForIntegration($commerceCode, $apiKey);
            }

            // Crear transacción
            $response = $transaction->create($buyOrder, $sessionId, $amount, $returnUrl);

            Log::info('Transbank: Transacción creada exitosamente', [
                'token' => $response->getToken(),
                'url' => $response->getUrl(),
            ]);

            return [
                'token' => $response->getToken(),
                'url' => $response->getUrl(),
            ];
        } catch (\Exception $e) {
            Log::error('Transbank: Error al crear transacción', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw new \Exception('Error al crear transacción en Transbank: '.$e->getMessage());
        }
    }

    /**
     * Confirmar transacción después del retorno
     *
     * @param  string  $token  Token de la transacción
     */
    public function confirmTransaction(string $token): array
    {
        try {
            Log::info('Transbank: Confirmando transacción', ['token' => $token]);

            // Obtener credenciales (usar defaults de integración si están vacías)
            $commerceCode = $this->commerceCode ?: WebpayPlus::INTEGRATION_COMMERCE_CODE;
            $apiKey = $this->apiKey ?: WebpayPlus::INTEGRATION_API_KEY;

            // Crear instancia de Transaction configurada para el ambiente
            if ($this->environment === 'production') {
                $transaction = Transaction::buildForProduction($commerceCode, $apiKey);
            } else {
                $transaction = Transaction::buildForIntegration($commerceCode, $apiKey);
            }

            $response = $transaction->commit($token);

            $result = [
                'vci' => $response->getVci(),
                'amount' => $response->getAmount(),
                'status' => $response->getStatus(),
                'buy_order' => $response->getBuyOrder(),
                'session_id' => $response->getSessionId(),
                'card_number' => $response->getCardDetail()?->getCardNumber() ?? null,
                'accounting_date' => $response->getAccountingDate(),
                'transaction_date' => $response->getTransactionDate(),
                'authorization_code' => $response->getAuthorizationCode(),
                'payment_type_code' => $response->getPaymentTypeCode(),
                'response_code' => $response->getResponseCode(),
                'installments_amount' => $response->getInstallmentsAmount() ?? null,
                'installments_number' => $response->getInstallmentsNumber() ?? null,
            ];

            Log::info('Transbank: Transacción confirmada', [
                'buy_order' => $result['buy_order'],
                'status' => $result['status'],
                'response_code' => $result['response_code'],
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Transbank: Error al confirmar transacción', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Error al confirmar transacción en Transbank: '.$e->getMessage());
        }
    }

    /**
     * Procesar webhook de Transbank
     *
     * Nota: Webpay Plus NO usa webhooks, la confirmación se hace mediante commit()
     * Este método se mantiene por compatibilidad con la interfaz
     */
    public function processWebhook(array $payload): bool
    {
        Log::info('Transbank: Webhook recibido (no implementado en Webpay Plus)', $payload);

        // Webpay Plus no usa webhooks, solo retorno POST
        return false;
    }

    /**
     * Verificar estado de una transacción
     *
     * @param  string  $transactionId  Token de la transacción
     */
    public function getTransactionStatus(string $transactionId): array
    {
        try {
            Log::info('Transbank: Consultando estado', ['token' => $transactionId]);

            // Obtener credenciales (usar defaults de integración si están vacías)
            $commerceCode = $this->commerceCode ?: WebpayPlus::INTEGRATION_COMMERCE_CODE;
            $apiKey = $this->apiKey ?: WebpayPlus::INTEGRATION_API_KEY;

            // Crear instancia de Transaction configurada para el ambiente
            if ($this->environment === 'production') {
                $transaction = Transaction::buildForProduction($commerceCode, $apiKey);
            } else {
                $transaction = Transaction::buildForIntegration($commerceCode, $apiKey);
            }

            $response = $transaction->status($transactionId);

            return [
                'status' => $response->getStatus(),
                'amount' => $response->getAmount(),
                'buy_order' => $response->getBuyOrder(),
                'session_id' => $response->getSessionId(),
                'authorization_code' => $response->getAuthorizationCode(),
                'response_code' => $response->getResponseCode(),
            ];
        } catch (\Exception $e) {
            Log::error('Transbank: Error al consultar estado', [
                'token' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Error al consultar estado en Transbank: '.$e->getMessage());
        }
    }

    /**
     * Reembolsar una transacción
     *
     * @param  string  $transactionId  Token de la transacción
     * @param  float|null  $amount  Monto a reembolsar (null = total)
     */
    public function refundTransaction(string $transactionId, ?float $amount = null): array
    {
        try {
            Log::info('Transbank: Solicitando reembolso', [
                'token' => $transactionId,
                'amount' => $amount,
            ]);

            // Obtener credenciales (usar defaults de integración si están vacías)
            $commerceCode = $this->commerceCode ?: WebpayPlus::INTEGRATION_COMMERCE_CODE;
            $apiKey = $this->apiKey ?: WebpayPlus::INTEGRATION_API_KEY;

            // Crear instancia de Transaction configurada para el ambiente
            if ($this->environment === 'production') {
                $transaction = Transaction::buildForProduction($commerceCode, $apiKey);
            } else {
                $transaction = Transaction::buildForIntegration($commerceCode, $apiKey);
            }

            $response = $transaction->refund($transactionId, $amount ?? 0);

            Log::info('Transbank: Reembolso procesado', [
                'type' => $response->getType(),
                'authorization_code' => $response->getAuthorizationCode(),
                'response_code' => $response->getResponseCode(),
            ]);

            return [
                'success' => $response->getResponseCode() === 0,
                'type' => $response->getType(), // REVERSED o NULLIFIED
                'authorization_code' => $response->getAuthorizationCode(),
                'authorization_date' => $response->getAuthorizationDate(),
                'nullified_amount' => $response->getNullifiedAmount() ?? null,
                'balance' => $response->getBalance() ?? null,
                'response_code' => $response->getResponseCode(),
            ];
        } catch (\Exception $e) {
            Log::error('Transbank: Error al procesar reembolso', [
                'token' => $transactionId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Error al procesar reembolso en Transbank: '.$e->getMessage());
        }
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
        return 'Transbank';
    }

    /**
     * Validar configuración de la pasarela
     */
    public function validateConfiguration(): bool
    {
        return ! empty($this->commerceCode) && ! empty($this->apiKey);
    }
}
