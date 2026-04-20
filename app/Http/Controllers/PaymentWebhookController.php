<?php

namespace App\Http\Controllers;

use App\Facades\PaymentGateway;
use App\Models\Payment;
use App\Services\PlantReservationService;
use BackedEnum;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PlantReservationService $reservationService,
    ) {}

    /**
     * Página puente para redirigir a Webpay Plus/Mall vía POST con token_ws.
     */
    public function transbankRedirect(Request $request)
    {
        $token = (string) $request->query('token_ws', '');
        $redirectUrl = (string) $request->query('tbk_url', '');

        Log::debug('Transbank: Bridge redirect request received', [
            'token' => $token,
            'token_is_empty' => $token === '',
            'redirect_url' => $redirectUrl,
            'redirect_url_is_empty' => $redirectUrl === '',
            'all_query_params' => $request->query(),
        ]);

        if ($token === '' || $redirectUrl === '') {
            Log::error('Transbank: Bridge missing required parameters', [
                'token_provided' => $token !== '',
                'redirect_url_provided' => $redirectUrl !== '',
            ]);

            return $this->redirectToFrontendResult('failed', null, [
                'error' => 'No se pudo preparar la redirección a Transbank.',
            ]);
        }

        if (! $this->isValidTransbankRedirectUrl($redirectUrl)) {
            Log::warning('Transbank: URL de redirección inválida detectada', [
                'redirect_url' => $redirectUrl,
            ]);

            return $this->redirectToFrontendResult('failed', null, [
                'error' => 'URL de pago invalida.',
            ]);
        }

        return response()->view('payments.transbank-redirect', [
            'token' => $token,
            'redirectUrl' => $redirectUrl,
        ]);
    }

    private function isValidTransbankRedirectUrl(string $redirectUrl): bool
    {
        if ($redirectUrl === '') {
            return false;
        }

        $parts = parse_url($redirectUrl);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '') {
            return false;
        }

        // Permite hosts oficiales de Webpay y subdominios futuros bajo transbank.cl.
        return in_array($host, [
            'webpay3gint.transbank.cl',
            'webpay3g.transbank.cl',
        ], true) || Str::endsWith($host, '.transbank.cl');
    }

    /**
     * Manejar retorno de Transbank (POST)
     *
     * Cuando el usuario completa o cancela el pago en Transbank,
     * es redirigido a esta URL vía POST con el token_ws
     */
    public function transbankReturn(Request $request)
    {
        try {
            $token = $request->input('token_ws');

            if (! $token) {
                $tbkToken = (string) ($request->input('TBK_TOKEN') ?? $request->input('tbk_token') ?? '');
                $tbkBuyOrder = (string) ($request->input('TBK_ORDEN_COMPRA') ?? $request->input('tbk_orden_compra') ?? '');

                Log::warning('Transbank: Retorno sin token_ws', [
                    'tbk_token' => $tbkToken,
                    'tbk_buy_order' => $tbkBuyOrder,
                ]);

                if ($tbkBuyOrder !== '') {
                    $payment = Payment::where('gateway_tx_id', $tbkBuyOrder)->first();
                    if ($payment) {
                        $payment->update([
                            'status' => \App\Enums\PaymentStatus::CANCELLED,
                            'metadata' => array_merge($payment->metadata ?? [], [
                                'transbank_abort_payload' => $request->all(),
                                'cancelled_at' => now()->toISOString(),
                            ]),
                        ]);

                        if ($payment->plant_id) {
                            $this->reservationService->releaseForPlant((int) $payment->plant_id, 'payment_cancelled');
                        }

                        return $this->redirectToFrontendResult('cancelled', $payment, [
                            'error' => 'El pago fue cancelado o expiro en Transbank.',
                        ]);
                    }
                }

                return $this->redirectToFrontendResult('failed', null, [
                    'error' => 'Transaccion cancelada o token no proporcionado por Transbank.',
                ]);
            }

            Log::debug('Transbank: Procesando retorno', ['token' => $token]);

            // Confirmar la transacción con Transbank
            $response = PaymentGateway::driver('transbank')->confirmTransaction($token);

            // Buscar el pago en la base de datos por buy_order o session_id
            $payment = Payment::where('gateway_tx_id', $response['buy_order'])
                ->orWhere('metadata->session_id', $response['session_id'])
                ->first();

            if (! $payment) {
                Log::warning('Transbank: Pago no encontrado', [
                    'buy_order' => $response['buy_order'],
                    'session_id' => $response['session_id'],
                ]);

                return $this->redirectToFrontendResult('failed', null, [
                    'error' => 'Pago no encontrado',
                ]);
            }

            // Actualizar el pago según el código de respuesta
            if ($response['response_code'] === 0) {
                // Transacción aprobada
                $payment->update([
                    'status' => \App\Enums\PaymentStatus::COMPLETED,
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'transbank_response' => $response,
                        'completed_at' => now()->toISOString(),
                    ]),
                ]);

                Log::debug('Transbank: Pago completado exitosamente', [
                    'payment_id' => $payment->id,
                    'buy_order' => $response['buy_order'],
                ]);

                // Completar la reserva de la planta
                if ($payment->plant_id) {
                    $this->reservationService->completeForPlant((int) $payment->plant_id);
                }

                return $this->redirectToFrontendResult('ok', $payment, [
                    'message' => 'Pago completado exitosamente',
                ]);
            } else {
                // Transacción rechazada
                $payment->update([
                    'status' => \App\Enums\PaymentStatus::FAILED,
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'transbank_response' => $response,
                        'failed_at' => now()->toISOString(),
                    ]),
                ]);

                Log::warning('Transbank: Pago rechazado', [
                    'payment_id' => $payment->id,
                    'response_code' => $response['response_code'],
                ]);

                // Liberar la reserva de la planta
                if ($payment->plant_id) {
                    $this->reservationService->releaseForPlant((int) $payment->plant_id, 'payment_rejected');
                }

                return $this->redirectToFrontendResult('failed', $payment, [
                    'error' => 'Pago rechazado por el banco',
                ]);
            }
        } catch (Exception $e) {
            Log::error('Transbank: Error procesando retorno', [
                'error' => $e->getMessage(),
                'token' => $request->input('token_ws'),
            ]);

            return $this->redirectToFrontendResult('failed', null, [
                'error' => 'Error al procesar el pago: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar webhook de Mercado Pago
     *
     * Mercado Pago envía notificaciones asíncronas sobre cambios en pagos
     */
    public function mercadopagoWebhook(Request $request)
    {
        try {
            // Verificar firma del webhook antes de procesar
            $xSignature = $request->header('x-signature');
            $rawBody = $request->getContent();

            $mercadoPagoService = PaymentGateway::driver('mercadopago');

            if (! $mercadoPagoService->verifyWebhookSignature($xSignature ?? '', $rawBody)) {
                Log::warning('MercadoPago: Webhook rechazado - firma inválida', [
                    'x-signature' => $xSignature ? substr($xSignature, 0, 20).'...' : 'missing',
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Invalid signature'], 403);
            }

            $data = $request->all();

            Log::debug('MercadoPago: Webhook recibido', $data);

            // Verificar el tipo de notificación
            $type = $request->input('type');
            $action = $request->input('action');

            if ($type === 'payment') {
                $paymentId = $request->input('data.id');

                if (! $paymentId) {
                    Log::warning('MercadoPago: Webhook sin payment ID');

                    return response()->json(['error' => 'Payment ID missing'], 400);
                }

                // Obtener información del pago desde Mercado Pago
                $paymentInfo = $mercadoPagoService->getTransactionStatus($paymentId);

                // Buscar el pago en nuestra base de datos
                $payment = Payment::where('gateway_tx_id', $paymentId)
                    ->orWhere('metadata->preference_id', $paymentInfo['external_reference'])
                    ->first();

                if (! $payment) {
                    Log::warning('MercadoPago: Pago no encontrado', [
                        'payment_id' => $paymentId,
                        'external_reference' => $paymentInfo['external_reference'],
                    ]);

                    return response()->json(['error' => 'Payment not found'], 404);
                }

                // Actualizar estado según el status de MP
                $newStatus = $this->mapMercadoPagoStatus($paymentInfo['status']);

                $payment->update([
                    'status' => $newStatus,
                    'gateway_tx_id' => $paymentId,
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'mercadopago_payment' => $paymentInfo,
                        'last_webhook_at' => now()->toISOString(),
                    ]),
                ]);

                Log::debug('MercadoPago: Pago actualizado', [
                    'payment_id' => $payment->id,
                    'mp_payment_id' => $paymentId,
                    'status' => $newStatus->value,
                ]);

                // Resolver reserva segun resultado del pago
                $plantId = $this->extractPlantIdFromExternalReference($paymentInfo['external_reference'] ?? '');
                if ($plantId) {
                    if ($newStatus->isCompleted()) {
                        $this->reservationService->completeForPlant($plantId);
                    } elseif ($newStatus->isFailed()) {
                        $this->reservationService->releaseForPlant($plantId, 'payment_failed');
                    }
                }

                return response()->json(['success' => true]);
            }

            // Otro tipo de notificación
            Log::debug('MercadoPago: Tipo de webhook no procesado', ['type' => $type]);

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            Log::error('MercadoPago: Error procesando webhook', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Manejar retorno de Mercado Pago (cuando el usuario vuelve de la pasarela)
     */
    public function mercadopagoReturn(Request $request)
    {
        try {
            $paymentId = $request->input('payment_id');
            $status = $request->input('status');
            $externalReference = $request->input('external_reference');

            Log::debug('MercadoPago: Retorno del usuario', [
                'payment_id' => $paymentId,
                'status' => $status,
                'external_reference' => $externalReference,
            ]);

            // Buscar el pago
            $payment = Payment::where('gateway_tx_id', $paymentId)
                ->orWhere('metadata->external_reference', $externalReference)
                ->first();

            if (! $payment) {
                return $this->redirectToFrontendResult('failed', null, [
                    'error' => 'Pago no encontrado',
                ]);
            }

            // Redirigir según el estado
            if ($status === 'approved') {
                return $this->redirectToFrontendResult('ok', $payment, [
                    'message' => 'Pago completado exitosamente',
                ]);
            } elseif ($status === 'pending') {
                return $this->redirectToFrontendResult('pending', $payment, [
                    'message' => 'Pago pendiente de confirmacion',
                ]);
            } else {
                return $this->redirectToFrontendResult('failed', $payment, [
                    'error' => 'Pago rechazado o cancelado',
                ]);
            }
        } catch (Exception $e) {
            Log::error('MercadoPago: Error procesando retorno', [
                'error' => $e->getMessage(),
            ]);

            return $this->redirectToFrontendResult('failed', null, [
                'error' => 'Error al procesar el pago',
            ]);
        }
    }

    private function redirectToFrontendResult(string $result, ?Payment $payment = null, array $extraParams = []): RedirectResponse
    {
        $params = [
            'result' => $result,
        ];

        if ($payment !== null) {
            $params['payment_id'] = (string) $payment->id;
            $params['status'] = $payment->status instanceof BackedEnum ? $payment->status->value : (string) $payment->status;
            $params['gateway'] = $payment->gateway instanceof BackedEnum ? $payment->gateway->value : (string) $payment->gateway;

            $statusToken = (string) data_get($payment->metadata, 'public_status_token', '');
            if ($statusToken !== '') {
                $params['status_token'] = $statusToken;
            }
        }

        $queryParams = array_filter(array_merge($params, $extraParams), fn ($value): bool => filled($value));

        $baseUrl = (string) config('payments.frontend_result_url', 'https://sale.ileben.cl/pago');

        if (str_contains($baseUrl, '?')) {
            return redirect()->away($baseUrl.'&'.http_build_query($queryParams));
        }

        return redirect()->away($baseUrl.'?'.http_build_query($queryParams));
    }

    /**
     * Mapear estados de Mercado Pago a nuestros estados
     */
    protected function mapMercadoPagoStatus(string $mpStatus): \App\Enums\PaymentStatus
    {
        return match ($mpStatus) {
            'approved' => \App\Enums\PaymentStatus::COMPLETED,
            'pending' => \App\Enums\PaymentStatus::PENDING,
            'in_process' => \App\Enums\PaymentStatus::PROCESSING,
            'authorized' => \App\Enums\PaymentStatus::AUTHORIZED,
            'rejected' => \App\Enums\PaymentStatus::FAILED,
            'cancelled' => \App\Enums\PaymentStatus::CANCELLED,
            'refunded' => \App\Enums\PaymentStatus::REFUNDED,
            default => \App\Enums\PaymentStatus::PENDING,
        };
    }

    /**
     * Extraer plant_id del buy_order de Transbank
     * Formato: ORDER-PLANT-{id}-{timestamp}
     */
    private function extractPlantIdFromBuyOrder(string $buyOrder): ?int
    {
        if (preg_match('/ORDER-PLANT-(\d+)-/', $buyOrder, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Extraer plant_id del external_reference de Mercado Pago
     * Formato: PLANT-{id}-{timestamp}
     */
    private function extractPlantIdFromExternalReference(string $ref): ?int
    {
        if (preg_match('/PLANT-(\d+)-/', $ref, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
