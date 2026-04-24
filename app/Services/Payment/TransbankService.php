<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Transbank\Webpay\WebpayPlus;
use Transbank\Webpay\WebpayPlus\MallTransaction;
use Transbank\Webpay\WebpayPlus\Transaction;

class TransbankService implements PaymentGatewayInterface
{
    private const INTEGRATION_MALL_CHILD_CODES = [
        WebpayPlus::INTEGRATION_MALL_CHILD_COMMERCE_CODE_1,
        WebpayPlus::INTEGRATION_MALL_CHILD_COMMERCE_CODE_2,
    ];

    private const INTEGRATION_API_KEY = WebpayPlus::INTEGRATION_API_KEY;

    protected array $config;

    protected string $environment;

    protected string $commerceCode;

    protected string $apiKey;

    protected string $returnUrl;

    protected bool $mallMode;

    protected array $commerceCodes;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->environment = $config['environment'] ?? 'integration';
        $this->commerceCode = $config['commerce_code'] ?? '';
        $this->apiKey = $config['api_key'] ?? '';
        $this->returnUrl = $config['return_url'] ?? '';
        $this->mallMode = $config['mall_mode'] ?? false;
        $this->commerceCodes = $config['commerce_codes'] ?? [];

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

    /**     * Resolver el código de comercio para una transacción
     * En modo mall: obtiene del proyecto, si no existe usa el default
     * En modo simple: siempre usa el default
     */
    protected function resolveCommerceCode(?Payment $payment = null): string
    {
        if ($this->environment === 'integration') {
            return $this->mallMode
                ? WebpayPlus::INTEGRATION_MALL_COMMERCE_CODE
                : WebpayPlus::INTEGRATION_COMMERCE_CODE;
        }

        $defaultCommerceCode = $this->commerceCode;

        // Si no estamos en mall mode o no hay payment, usar código default
        if (! $this->mallMode || ! $payment) {
            return $defaultCommerceCode;
        }

        // Cargar proyecto si existe
        if ($payment->project) {
            $projectCode = $payment->project->transbank_commerce_code;
            if ($projectCode) {
                Log::debug('Transbank: Resolviendo código para proyecto', [
                    'project_id' => $payment->project_id,
                    'project_slug' => $payment->project->slug,
                    'commerce_code' => $projectCode,
                ]);

                return $projectCode;
            }
        }

        // Fallback al código default
        Log::warning('Transbank: Código de proyecto no encontrado, usando default', [
            'project_id' => $payment?->project_id,
            'payment_id' => $payment?->id,
        ]);

        return $defaultCommerceCode;
    }

    /**
     * Obtener código de comercio hijo para Webpay Plus Mall.
     */
    protected function resolveMallChildCommerceCode(array $data, ?Payment $payment = null): string
    {
        $configuredChildCode = null;

        if (! empty($data['child_commerce_code'])) {
            $configuredChildCode = (string) $data['child_commerce_code'];
        }

        if ($configuredChildCode === null && $payment?->project) {
            $projectCode = $payment->project->getRawOriginal('transbank_commerce_code');
            if (! empty($projectCode)) {
                $configuredChildCode = (string) $projectCode;
            }
        }

        if ($this->environment === 'integration') {
            if ($configuredChildCode !== null && in_array($configuredChildCode, self::INTEGRATION_MALL_CHILD_CODES, true)) {
                return $configuredChildCode;
            }

            if ($configuredChildCode !== null) {
                Log::warning('Transbank: child_commerce_code no válido para integración Mall. Se utilizará child de integración.', [
                    'configured_child_commerce_code' => $configuredChildCode,
                    'resolved_child_commerce_code' => WebpayPlus::INTEGRATION_MALL_CHILD_COMMERCE_CODE_1,
                ]);
            }

            return WebpayPlus::INTEGRATION_MALL_CHILD_COMMERCE_CODE_1;
        }

        if ($configuredChildCode !== null) {
            return $configuredChildCode;
        }

        throw new \InvalidArgumentException('Transbank Mall requiere child_commerce_code para crear la transacción.');
    }

    protected function buildTransactionClient(string $commerceCode): Transaction|MallTransaction
    {
        $apiKey = $this->environment === 'integration'
            ? self::INTEGRATION_API_KEY
            : $this->apiKey;

        if ($this->mallMode) {
            if ($this->environment === 'production') {
                return MallTransaction::buildForProduction($apiKey, $commerceCode);
            }

            return MallTransaction::buildForIntegration($apiKey, $commerceCode);
        }

        if ($this->environment === 'production') {
            return Transaction::buildForProduction($apiKey, $commerceCode);
        }

        return Transaction::buildForIntegration($apiKey, $commerceCode);
    }

    /**     * Crear una transacción Webpay Plus
     *
     * @param  array  $data  Datos de la transacción
     *                       - amount: Monto de la transacción
     *                       - buy_order: Orden de compra (máx 26 caracteres)
     *                       - session_id: ID de sesión (máx 61 caracteres)
     *                       - return_url: URL de retorno (opcional, usa default si no se provee)
     * @param  Payment|null  $payment  Pago asociado (usado en mall mode para resolver código dinámico)
     */
    public function createTransaction(array $data, ?Payment $payment = null): array
    {
        try {
            $amount = $data['amount'];
            $buyOrder = $data['buy_order'] ?? 'ORDER-'.time();
            $sessionId = $data['session_id'] ?? uniqid('session-');
            $returnUrl = $data['return_url'] ?? $this->returnUrl;

            // Resolver código de comercio (mall o default)
            $commerceCode = $this->resolveCommerceCode($payment);

            Log::debug('Transbank: Creando transacción', [
                'amount' => $amount,
                'buy_order' => $buyOrder,
                'session_id' => $sessionId,
                'plant_name' => $data['plant_name'] ?? null,
                'plant_id' => $data['plant_id'] ?? null,
                'commerce_code' => $commerceCode,
                'environment' => $this->environment,
                'mall_mode' => $this->mallMode,
            ]);

            $transaction = $this->buildTransactionClient($commerceCode);

            if ($this->mallMode) {
                $childCommerceCode = $this->resolveMallChildCommerceCode($data, $payment);
                $childBuyOrder = (string) ($data['child_buy_order'] ?? ('CHILD-'.$buyOrder));
                $details = [
                    [
                        'amount' => (int) $amount,
                        'commerce_code' => $childCommerceCode,
                        'buy_order' => $childBuyOrder,
                    ],
                ];

                /** @var MallTransaction $transaction */
                $response = $transaction->create($buyOrder, $sessionId, $returnUrl, $details);
            } else {
                /** @var Transaction $transaction */
                $response = $transaction->create($buyOrder, $sessionId, $amount, $returnUrl);
            }

            $token = $response->getToken();
            $url = $response->getUrl();

            Log::debug('Transbank: Transacción creada exitosamente', [
                'token' => $token,
                'token_is_null' => $token === null,
                'token_is_empty' => $token === '',
                'url' => $url,
                'url_is_null' => $url === null,
                'url_is_empty' => $url === '',
                'response_object' => method_exists($response, 'toArray') ? $response->toArray() : (array) $response,
                'commerce_code' => $commerceCode,
                'buy_order' => $buyOrder,
                'session_id' => $sessionId,
            ]);

            if (empty($token) || empty($url)) {
                Log::error('Transbank: Response inválida - token o URL vacíos', [
                    'token' => $token,
                    'url' => $url,
                    'buy_order' => $buyOrder,
                    'session_id' => $sessionId,
                ]);
            }

            return [
                'token' => $token,
                'url' => $url,
            ];
        } catch (\Exception $e) {
            Log::error('Transbank: Error al crear transacción', [
                'error' => $e->getMessage(),
                'data' => $data,
                'payment_id' => $payment?->id,
            ]);

            throw new \Exception('Error al crear transacción en Transbank: '.$e->getMessage());
        }
    }

    /**
     * Confirmar transacción después del retorno
     *
     * @param  string  $token  Token de la transacción
     * @param  Payment|null  $payment  Pago asociado (usado en mall mode para validar código)
     */
    public function confirmTransaction(string $token, ?Payment $payment = null): array
    {
        try {
            // Resolver código de comercio (mall o default)
            $commerceCode = $this->resolveCommerceCode($payment);

            Log::debug('Transbank: Confirmando transacción', [
                'token' => $token,
                'commerce_code' => $commerceCode,
                'mall_mode' => $this->mallMode,
            ]);

            $transaction = $this->buildTransactionClient($commerceCode);

            $response = $transaction->commit($token);

            $firstDetail = null;
            if ($this->mallMode && method_exists($response, 'getDetails')) {
                $details = $response->getDetails() ?? [];
                $firstDetail = $details[0] ?? null;
            }

            $amountValue = $this->mallMode
                ? $firstDetail?->getAmount()
                : $response->getAmount();
            $statusValue = $this->mallMode
                ? $firstDetail?->getStatus()
                : $response->getStatus();
            $authorizationCode = $this->mallMode
                ? $firstDetail?->getAuthorizationCode()
                : $response->getAuthorizationCode();
            $paymentTypeCode = $this->mallMode
                ? $firstDetail?->getPaymentTypeCode()
                : $response->getPaymentTypeCode();
            $responseCode = $this->mallMode
                ? $firstDetail?->getResponseCode()
                : $response->getResponseCode();
            $installmentsAmount = $this->mallMode
                ? $firstDetail?->getInstallmentsAmount()
                : ($response->getInstallmentsAmount() ?? null);
            $installmentsNumber = $this->mallMode
                ? $firstDetail?->getInstallmentsNumber()
                : ($response->getInstallmentsNumber() ?? null);

            $result = [
                'vci' => $response->getVci(),
                'amount' => $amountValue,
                'status' => $statusValue,
                'buy_order' => $response->getBuyOrder(),
                'session_id' => $response->getSessionId(),
                'card_number' => $this->mallMode
                    ? ($response->getCardNumber() ?? null)
                    : ($response->getCardDetail()?->getCardNumber() ?? null),
                'accounting_date' => $response->getAccountingDate(),
                'transaction_date' => $response->getTransactionDate(),
                'authorization_code' => $authorizationCode,
                'payment_type_code' => $paymentTypeCode,
                'response_code' => $responseCode,
                'installments_amount' => $installmentsAmount,
                'installments_number' => $installmentsNumber,
                'details' => $this->mallMode && method_exists($response, 'getDetails') ? collect($response->getDetails() ?? [])->map(static fn ($detail): array => [
                    'amount' => $detail->getAmount(),
                    'status' => $detail->getStatus(),
                    'authorization_code' => $detail->getAuthorizationCode(),
                    'payment_type_code' => $detail->getPaymentTypeCode(),
                    'response_code' => $detail->getResponseCode(),
                    'installments_amount' => $detail->getInstallmentsAmount(),
                    'installments_number' => $detail->getInstallmentsNumber(),
                    'commerce_code' => $detail->getCommerceCode(),
                    'buy_order' => $detail->getBuyOrder(),
                    'balance' => $detail->getBalance(),
                ])->all() : null,
                'commerce_code' => $commerceCode,
            ];

            Log::debug('Transbank: Transacción confirmada', [
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
        Log::debug('Transbank: Webhook recibido (no implementado en Webpay Plus)', $payload);

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
            Log::debug('Transbank: Consultando estado', ['token' => $transactionId]);

            // Obtener credenciales (usar defaults de integración si están vacías)
            $commerceCode = $this->resolveCommerceCode();

            $transaction = $this->buildTransactionClient($commerceCode);

            $response = $transaction->status($transactionId);

            $firstDetail = null;
            if ($this->mallMode && method_exists($response, 'getDetails')) {
                $details = $response->getDetails() ?? [];
                $firstDetail = $details[0] ?? null;
            }

            $statusValue = $this->mallMode
                ? $firstDetail?->getStatus()
                : $response->getStatus();
            $amountValue = $this->mallMode
                ? $firstDetail?->getAmount()
                : $response->getAmount();
            $authorizationCode = $this->mallMode
                ? $firstDetail?->getAuthorizationCode()
                : $response->getAuthorizationCode();
            $responseCode = $this->mallMode
                ? $firstDetail?->getResponseCode()
                : $response->getResponseCode();

            return [
                'status' => $statusValue,
                'amount' => $amountValue,
                'buy_order' => $response->getBuyOrder(),
                'session_id' => $response->getSessionId(),
                'authorization_code' => $authorizationCode,
                'response_code' => $responseCode,
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
            Log::debug('Transbank: Solicitando reembolso', [
                'token' => $transactionId,
                'amount' => $amount,
            ]);

            $commerceCode = $this->resolveCommerceCode();
            $transaction = $this->buildTransactionClient($commerceCode);

            if ($this->mallMode) {
                $statusResponse = $transaction->status($transactionId);
                $details = $statusResponse->getDetails() ?? [];
                $firstDetail = $details[0] ?? null;

                if (! $firstDetail) {
                    throw new \RuntimeException('No se encontraron detalles de transacción Mall para procesar el reembolso.');
                }

                $refundAmount = $amount ?? (float) ($firstDetail->getAmount() ?? 0);
                $response = $transaction->refund(
                    $transactionId,
                    (string) $firstDetail->getBuyOrder(),
                    (string) $firstDetail->getCommerceCode(),
                    $refundAmount,
                );
            } else {
                $response = $transaction->refund($transactionId, $amount ?? 0);
            }

            Log::debug('Transbank: Reembolso procesado', [
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
        if ($this->environment === 'integration') {
            return true;
        }

        return ! empty($this->commerceCode) && ! empty($this->apiKey);
    }
}
