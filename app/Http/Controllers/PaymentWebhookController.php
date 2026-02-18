<?php

namespace App\Http\Controllers;

use App\Facades\PaymentGateway;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
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
                Log::warning('Transbank: Retorno sin token_ws');

                return redirect()->route('payment.failed')
                    ->with('error', 'Token de transacción no proporcionado');
            }

            Log::info('Transbank: Procesando retorno', ['token' => $token]);

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

                return redirect()->route('payment.failed')
                    ->with('error', 'Pago no encontrado');
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

                Log::info('Transbank: Pago completado exitosamente', [
                    'payment_id' => $payment->id,
                    'buy_order' => $response['buy_order'],
                ]);

                return redirect()->route('payment.success', ['payment' => $payment->id])
                    ->with('success', 'Pago completado exitosamente');
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

                return redirect()->route('payment.failed', ['payment' => $payment->id])
                    ->with('error', 'Pago rechazado por el banco');
            }
        } catch (\Exception $e) {
            Log::error('Transbank: Error procesando retorno', [
                'error' => $e->getMessage(),
                'token' => $request->input('token_ws'),
            ]);

            return redirect()->route('payment.failed')
                ->with('error', 'Error al procesar el pago: '.$e->getMessage());
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
            $data = $request->all();

            Log::info('MercadoPago: Webhook recibido', $data);

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
                $paymentInfo = PaymentGateway::driver('mercadopago')->getTransactionStatus($paymentId);

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

                Log::info('MercadoPago: Pago actualizado', [
                    'payment_id' => $payment->id,
                    'mp_payment_id' => $paymentId,
                    'status' => $newStatus->value,
                ]);

                return response()->json(['success' => true]);
            }

            // Otro tipo de notificación
            Log::info('MercadoPago: Tipo de webhook no procesado', ['type' => $type]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
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

            Log::info('MercadoPago: Retorno del usuario', [
                'payment_id' => $paymentId,
                'status' => $status,
                'external_reference' => $externalReference,
            ]);

            // Buscar el pago
            $payment = Payment::where('gateway_tx_id', $paymentId)
                ->orWhere('metadata->external_reference', $externalReference)
                ->first();

            if (! $payment) {
                return redirect()->route('payment.failed')
                    ->with('error', 'Pago no encontrado');
            }

            // Redirigir según el estado
            if ($status === 'approved') {
                return redirect()->route('payment.success', ['payment' => $payment->id])
                    ->with('success', 'Pago completado exitosamente');
            } elseif ($status === 'pending') {
                return redirect()->route('payment.pending', ['payment' => $payment->id])
                    ->with('info', 'Pago pendiente de confirmación');
            } else {
                return redirect()->route('payment.failed', ['payment' => $payment->id])
                    ->with('error', 'Pago rechazado o cancelado');
            }
        } catch (\Exception $e) {
            Log::error('MercadoPago: Error procesando retorno', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('payment.failed')
                ->with('error', 'Error al procesar el pago');
        }
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
}
