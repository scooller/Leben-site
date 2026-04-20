<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutInitiateRequest;
use App\Models\Plant;
use App\Models\PlantReservation;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\FinMail\FinMailNotificationService;
use App\Services\Payment\ManualPaymentService;
use App\Services\Payment\TransbankService;
use App\Services\PlantReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PlantReservationService $reservationService,
        private readonly FinMailNotificationService $finMailNotificationService,
    ) {}

    /**
     * Iniciar sesión de pago
     * Retorna la URL de redirección a la pasarela de pago
     */
    public function initiate(CheckoutInitiateRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $reservation = null;

            // Validar reserva si se proporciona session_token
            if (! empty($validated['session_token'])) {
                $reservation = $this->reservationService->validateReservationForCheckout(
                    (int) $validated['plant_id'],
                    $validated['session_token'],
                );
            }

            $payerUser = $this->resolvePayerUser($validated);

            // Obtener la planta con su proyecto
            $plant = Plant::with('proyecto')->findOrFail($validated['plant_id']);

            if ($validated['gateway'] === 'transbank' && ! $this->projectHasCommerceCode($plant->proyecto)) {
                return response()->json([
                    'message' => 'Este proyecto no tiene Código de Comercio configurado.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Usar valor_reserva_exigido_defecto_peso del proyecto como monto a cobrar
            $reservaDefectoPeso = $plant->proyecto?->valor_reserva_exigido_defecto_peso;
            $amount = $reservaDefectoPeso !== null
                ? (float) $reservaDefectoPeso * $validated['quantity']
                : (float) $plant->precio_base * $validated['quantity'];
            $description = "{$validated['quantity']}x {$plant->name}";

            if ($validated['gateway'] === 'manual') {
                return $this->initiateManual($payerUser, $plant, $reservation, $amount, $description, $validated);
            }

            // Iniciar transacción según la pasarela
            if ($validated['gateway'] === 'transbank') {
                return $this->initiateTransbank($payerUser, $plant, $reservation, $amount, $description, $validated['quantity'], $validated);
            }

            return $this->initiateMercadoPago(
                $plant,
                $amount,
                $description,
                $validated['quantity'],
                $validated['email'],
            );
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al iniciar el checkout',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Iniciar pago manual.
     */
    /** @param array<string, mixed> $billing */
    private function initiateManual(User $user, Plant $plant, ?PlantReservation $reservation, float $amount, string $description, array $billing = []): JsonResponse
    {
        if (! $this->projectHasManualPaymentData($plant->proyecto)) {
            return response()->json([
                'message' => 'El pago manual no esta disponible para este proyecto.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $config = $this->manualGatewayConfig($plant->proyecto);

        if (! ($config['enabled'] ?? false)) {
            return response()->json([
                'message' => 'El pago manual no esta disponible actualmente.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $reservation) {
            return response()->json([
                'message' => 'Debes contar con una reserva activa para iniciar un pago manual.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $service = new ManualPaymentService($config);
        $manualTransaction = $service->createTransaction([
            'user_id' => $user->id,
            'plant_id' => $plant->id,
            'amount' => $amount,
            'description' => $description,
        ]);

        $expiresAt = filled($manualTransaction['expires_at'] ?? null)
            ? Carbon::parse($manualTransaction['expires_at'])
            : null;

        $payment = $user->payments()->create([
            'project_id' => $plant->proyecto?->id,
            'plant_id' => $plant->id,
            'gateway' => 'manual',
            'gateway_tx_id' => $manualTransaction['reference'],
            'amount' => $amount,
            'currency' => config('payments.currency', 'CLP'),
            'status' => $manualTransaction['status'],
            'billing_name' => $this->billingName($billing),
            'billing_email' => $this->billingEmail($billing),
            'billing_phone' => $this->billingPhone($billing),
            'billing_rut' => $this->billingRut($billing),
            'metadata' => [
                'description' => $description,
                'manual_payment_reference' => $manualTransaction['reference'],
                'manual_payment_instructions' => $manualTransaction['instructions'] ?? null,
                'manual_payment_bank_accounts' => $manualTransaction['bank_accounts'] ?? [],
                'manual_payment_requires_proof' => (bool) ($manualTransaction['requires_proof'] ?? true),
                'manual_payment_expires_at' => $expiresAt?->toISOString(),
                'manual_payment_proof_submitted' => false,
                'manual_payment_link' => $config['payment_link'] ?? null,
            ],
        ]);

        if ($expiresAt !== null) {
            $this->reservationService->extendForManualPayment($reservation, $expiresAt, [
                'manual_payment_id' => $payment->id,
                'manual_payment_reference' => $manualTransaction['reference'],
            ]);
        }

        if ($reservation !== null) {
            $this->finMailNotificationService->sendManualReservationCreated($payment, $reservation->fresh());
        }

        return response()->json([
            'flow' => 'manual',
            'gateway' => 'manual',
            'payment_id' => $payment->id,
            'reference' => $manualTransaction['reference'],
            'amount' => $amount,
            'currency' => $payment->currency,
            'description' => $description,
            'status' => is_object($payment->status) ? $payment->status->value : (string) $payment->status,
            'instructions' => $manualTransaction['instructions'] ?? null,
            'bank_accounts' => $manualTransaction['bank_accounts'] ?? [],
            'payment_link' => $config['payment_link'] ?? null,
            'requires_proof' => (bool) ($manualTransaction['requires_proof'] ?? true),
            'expires_at' => $expiresAt?->toISOString(),
        ]);
    }

    /**
     * Iniciar pago con Transbank
     */
    /** @param array<string, mixed> $billing */
    private function initiateTransbank(User $user, Plant $plant, ?PlantReservation $reservation, float $amount, string $description, int $quantity, array $billing = []): JsonResponse
    {
        try {
            $config = config('payments.gateways.transbank', []);
            $transbankEnv = $config['environment'] ?? 'integration';
            $hasCredentials = ! empty($config['commerce_code']) && ! empty($config['api_key']);
            $mallMode = (bool) ($config['mall_mode'] ?? false);

            if ($transbankEnv === 'integration' && ! $hasCredentials) {
                $config['commerce_code'] = $mallMode ? '597055555535' : '597055555532';
                $config['api_key'] = '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C';
            }

            // Si no hay configuración válida, simular
            if (empty($config['commerce_code']) || empty($config['api_key'])) {
                return $this->simulateCheckout('transbank', $plant, $amount, $description);
            }

            $service = new TransbankService($config);

            $plantReference = Str::upper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $plant->name), 0, 6));
            if ($plantReference === '') {
                $plantReference = (string) $plant->id;
            }

            $timestampRef = substr((string) now()->timestamp, -8);
            $buyOrder = 'OP'.$plantReference.$timestampRef;

            $requestPayload = [
                'amount' => (int) $amount,
                'buy_order' => $buyOrder,
                'session_id' => 'SESSION-'.uniqid(),
                'return_url' => route('payment.transbank.return'),
                'plant_name' => (string) $plant->name,
                'plant_id' => $plant->id,
            ];

            if ($mallMode) {
                $childCommerceCode = $plant->proyecto?->getRawOriginal('transbank_commerce_code');

                if (! filled($childCommerceCode)) {
                    return response()->json([
                        'message' => 'Este proyecto no tiene código de comercio hijo para Webpay Plus Mall.',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $requestPayload['child_commerce_code'] = (string) $childCommerceCode;
                $requestPayload['child_buy_order'] = 'CH'.$plantReference.$timestampRef;
            }

            $payment = $user->payments()->create([
                'project_id' => $plant->proyecto?->id,
                'plant_id' => $plant->id,
                'gateway' => 'transbank',
                'gateway_tx_id' => $buyOrder,
                'amount' => $amount,
                'currency' => config('payments.currency', 'CLP'),
                'status' => \App\Enums\PaymentStatus::PENDING,
                'billing_name' => $this->billingName($billing),
                'billing_email' => $this->billingEmail($billing),
                'billing_phone' => $this->billingPhone($billing),
                'billing_rut' => $this->billingRut($billing),
                'metadata' => [
                    'description' => $description,
                    'session_id' => $requestPayload['session_id'],
                    'reservation_session_token' => $reservation?->session_token,
                    'public_status_token' => (string) Str::uuid(),
                    'transbank_child_buy_order' => $requestPayload['child_buy_order'] ?? null,
                    'transbank_child_commerce_code' => $requestPayload['child_commerce_code'] ?? null,
                ],
            ]);

            $response = $service->createTransaction($requestPayload);

            $payment->update([
                'metadata' => array_merge($payment->metadata ?? [], [
                    'transbank_token' => $response['token'] ?? null,
                    'transbank_redirect_url' => $response['url'] ?? null,
                ]),
            ]);

            Log::info('Checkout: Transbank transaction response', [
                'token' => $response['token'] ?? null,
                'token_is_null' => ($response['token'] ?? null) === null,
                'token_is_empty' => empty($response['token'] ?? null),
                'url' => $response['url'] ?? null,
                'url_is_null' => ($response['url'] ?? null) === null,
                'buy_order' => $requestPayload['buy_order'],
            ]);

            return response()->json([
                'gateway' => 'transbank',
                'redirect_url' => $response['url'],
                'token' => $response['token'],
                'payment_id' => $payment->id,
                'payment_status_token' => data_get($payment->metadata, 'public_status_token'),
                'amount' => $amount,
                'description' => $description,
                'environment' => $transbankEnv,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al iniciar pago con Transbank',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Iniciar pago con Mercado Pago
     */
    private function initiateMercadoPago(Plant $plant, float $amount, string $description, int $quantity, string $payerEmail): JsonResponse
    {
        try {
            // Verificar configuración
            if (! config('services.mercadopago.public_key') || ! config('services.mercadopago.access_token')) {
                return $this->simulateCheckout('mercadopago', $plant, $amount, $description);
            }

            $service = new \App\Services\Payment\MercadoPagoService(config('services.mercadopago'));

            $response = $service->createTransaction([
                'amount' => $amount,
                'description' => $description,
                'external_reference' => 'PLANT-'.$plant->id.'-'.now()->timestamp,
                'currency' => 'CLP',
                'payer_email' => $payerEmail,
            ]);

            return response()->json([
                'gateway' => 'mercadopago',
                'redirect_url' => $response['init_point'] ?? $response['sandbox_init_point'],
                'preference_id' => $response['preference_id'],
                'amount' => $amount,
                'description' => $description,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al iniciar pago con Mercado Pago',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Simular checkout cuando no hay credenciales reales configuradas
     * Para propósitos de desarrollo/demostración
     */
    private function simulateCheckout(string $gateway, Plant $plant, float $amount, string $description): JsonResponse
    {
        if ($gateway === 'transbank') {
            $redirectUrl = route('payment.transbank.return');
        } else {
            $redirectUrl = route('payment.mercadopago.return');
        }

        return response()->json([
            'gateway' => $gateway,
            'redirect_url' => $redirectUrl,
            'amount' => $amount,
            'description' => $description,
            'simulated' => true,
            'message' => "Checkout simulado - Pasarela $gateway no configurada",
        ]);
    }

    /**
     * Obtener pasarelas disponibles
     */
    public function availableGateways(Request $request): JsonResponse
    {
        $gateways = [];
        $siteSettings = SiteSetting::current();
        $project = null;

        $plantId = $request->query('plant_id');

        if (filled($plantId)) {
            $plant = Plant::query()
                ->with('proyecto')
                ->find($plantId);

            $project = $plant?->proyecto;
        }

        // Verificar Transbank (en modo integration siempre está disponible)
        $transbankEnv = config('payments.gateways.transbank.environment', 'integration');
        $hasTransbankCredentials = config('payments.gateways.transbank.commerce_code') && config('payments.gateways.transbank.api_key');
        $transbankEnabled = (bool) ($siteSettings->gateway_transbank_enabled ?? config('payments.gateways.transbank.enabled', true));

        $transbankAvailableForProject = ! filled($plantId)
            ? true
            : $this->projectHasCommerceCode($project);

        if ($transbankEnabled && ($transbankEnv === 'integration' || $hasTransbankCredentials) && $transbankAvailableForProject) {
            $gateways[] = [
                'id' => 'transbank',
                'name' => 'Webpay (Transbank)',
                'flow' => 'redirect',
                'description' => $transbankEnv === 'integration'
                    ? 'Modo de prueba - Tarjeta de crédito o débito'
                    : 'Paga con tarjeta de crédito o débito',
            ];
        }

        // Verificar Mercado Pago
        $mercadoPagoEnabled = (bool) ($siteSettings->gateway_mercadopago_enabled ?? config('payments.gateways.mercadopago.enabled', false));

        if ($mercadoPagoEnabled && config('services.mercadopago.public_key') && config('services.mercadopago.access_token')) {
            $gateways[] = [
                'id' => 'mercadopago',
                'name' => 'Mercado Pago',
                'flow' => 'redirect',
                'description' => 'Paga con múltiples métodos',
            ];
        }

        $manualConfig = $this->manualGatewayConfig($project);
        $manualAvailableForProject = $project === null || $this->projectHasManualPaymentData($project);

        if (($manualConfig['enabled'] ?? false) && $manualAvailableForProject) {
            $gateways[] = [
                'id' => 'manual',
                'name' => $manualConfig['name'] ?? 'Pago Manual',
                'flow' => 'manual',
                'description' => ($manualConfig['requires_proof'] ?? true)
                    ? 'Genera una referencia unica, transfiere y sube tu comprobante antes del vencimiento.'
                    : 'Transferencia bancaria u otro metodo offline.',
            ];
        }

        return response()->json([
            'gateways' => $gateways,
            'count' => count($gateways),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function manualGatewayConfig(?Proyecto $project = null): array
    {
        $settings = SiteSetting::current();
        $defaultConfig = config('payments.gateways.manual', []);
        $baseConfig = [
            'name' => $defaultConfig['name'] ?? 'Pago Manual',
            'requires_proof' => (bool) ($defaultConfig['requires_proof'] ?? true),
            'auto_expire_hours' => $defaultConfig['auto_expire_hours'] ?? null,
            'instructions' => null,
            'bank_accounts' => [],
            'payment_link' => null,
        ];
        $settingsConfig = is_array($settings->gateway_manual_config) ? $settings->gateway_manual_config : [];

        $projectConfig = [];

        if ($project) {
            if (filled($project->manual_payment_instructions)) {
                $projectConfig['instructions'] = (string) $project->manual_payment_instructions;
            }

            if (is_array($project->manual_payment_bank_accounts) && ! empty($project->manual_payment_bank_accounts)) {
                $projectConfig['bank_accounts'] = $project->manual_payment_bank_accounts;
            }

            if (filled($project->manual_payment_link)) {
                $projectConfig['payment_link'] = (string) $project->manual_payment_link;
            }
        }

        return array_replace_recursive($baseConfig, $settingsConfig, $projectConfig, [
            'enabled' => (bool) ($settings->gateway_manual_enabled ?? ($defaultConfig['enabled'] ?? false)),
        ]);
    }

    private function projectHasManualPaymentData(?Proyecto $project): bool
    {
        if (! $project) {
            return false;
        }

        if (filled($project->manual_payment_link) || filled($project->manual_payment_instructions)) {
            return true;
        }

        return is_array($project->manual_payment_bank_accounts) && ! empty($project->manual_payment_bank_accounts);
    }

    private function projectHasCommerceCode(?Proyecto $project): bool
    {
        if (! $project) {
            return false;
        }

        return filled($project->getRawOriginal('transbank_commerce_code'));
    }

    /**
     * @param  array<string, mixed>  $billing
     */
    private function resolvePayerUser(array $billing): User
    {
        $email = $this->billingEmail($billing);
        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser) {
            return $existingUser;
        }

        $rut = $this->billingRut($billing);
        $safeRut = $rut !== null && User::query()->where('rut', $rut)->exists() ? null : $rut;

        return User::query()->create([
            'name' => $this->billingName($billing) ?? $email,
            'email' => $email,
            'user_type' => 'customer',
            'phone' => $this->billingPhone($billing),
            'rut' => $safeRut,
            'password' => $email,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $billing
     */
    private function billingName(array $billing): ?string
    {
        $value = trim((string) ($billing['name'] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $billing
     */
    private function billingEmail(array $billing): string
    {
        return Str::lower(trim((string) ($billing['email'] ?? '')));
    }

    /**
     * @param  array<string, mixed>  $billing
     */
    private function billingPhone(array $billing): ?string
    {
        $value = trim((string) ($billing['phone'] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $billing
     */
    private function billingRut(array $billing): ?string
    {
        $value = Str::upper(trim((string) ($billing['rut'] ?? '')));

        return $value !== '' ? $value : null;
    }
}
