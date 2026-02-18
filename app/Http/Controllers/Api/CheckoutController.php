<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CheckoutController extends Controller
{
    /**
     * Iniciar sesión de pago
     * Retorna la URL de redirección a la pasarela de pago
     */
    public function initiate(Request $request)
    {
        try {
            $validated = $request->validate([
                'plant_id' => 'required|integer',
                'quantity' => 'required|integer|min:1',
                'gateway' => 'required|in:transbank,mercadopago',
            ]);

            // Obtener la planta
            $plant = Plant::findOrFail($validated['plant_id']);

            // Calcular monto total usando precio_base
            $amount = (float) $plant->precio_base * $validated['quantity'];
            $description = "{$validated['quantity']}x {$plant->name}";

            // Iniciar transacción según la pasarela
            if ($validated['gateway'] === 'transbank') {
                return $this->initiateTransbank($plant, $amount, $description, $validated['quantity']);
            } else {
                return $this->initiateMercadoPago($plant, $amount, $description, $validated['quantity']);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validación fallida',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al iniciar el checkout',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Iniciar pago con Transbank
     */
    private function initiateTransbank(Plant $plant, float $amount, string $description, int $quantity)
    {
        try {
            $transbankEnv = config('services.transbank.environment', 'integration');
            $hasCredentials = config('services.transbank.commerce_code') && config('services.transbank.api_key');

            // En modo integration, usar credenciales de prueba de Transbank
            if ($transbankEnv === 'integration' && ! $hasCredentials) {
                // Credenciales de integración por defecto de Transbank
                $config = [
                    'environment' => 'integration',
                    'commerce_code' => '597055555532',  // Código de comercio de prueba
                    'api_key' => '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C',  // API Key de prueba
                ];
            } else {
                $config = config('services.transbank');
            }

            // Si no hay configuración válida, simular
            if (empty($config['commerce_code']) || empty($config['api_key'])) {
                return $this->simulateCheckout('transbank', $plant, $amount, $description);
            }

            $service = new \App\Services\Payment\TransbankService($config);

            $response = $service->createTransaction([
                'amount' => (int) $amount,
                'buy_order' => 'ORDER-PLANT-'.$plant->id.'-'.time(),
                'session_id' => 'SESSION-'.uniqid(),
                'return_url' => route('payment.transbank.return'),
            ]);

            return response()->json([
                'gateway' => 'transbank',
                'redirect_url' => $response['url'],
                'token' => $response['token'],
                'amount' => $amount,
                'description' => $description,
                'environment' => $transbankEnv,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al iniciar pago con Transbank',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Iniciar pago con Mercado Pago
     */
    private function initiateMercadoPago(Plant $plant, float $amount, string $description, int $quantity)
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
                'external_reference' => 'PLANT-'.$plant->id.'-'.time(),
                'currency' => 'CLP',
            ]);

            return response()->json([
                'gateway' => 'mercadopago',
                'redirect_url' => $response['init_point'] ?? $response['sandbox_init_point'],
                'preference_id' => $response['preference_id'],
                'amount' => $amount,
                'description' => $description,
            ]);
        } catch (\Exception $e) {
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
    private function simulateCheckout(string $gateway, Plant $plant, float $amount, string $description)
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
    public function availableGateways()
    {
        $gateways = [];

        // Verificar Transbank (en modo integration siempre está disponible)
        $transbankEnv = config('services.transbank.environment', 'integration');
        $hasTransbankCredentials = config('services.transbank.commerce_code') && config('services.transbank.api_key');

        if ($transbankEnv === 'integration' || $hasTransbankCredentials) {
            $gateways[] = [
                'id' => 'transbank',
                'name' => 'Webpay (Transbank)',
                'description' => $transbankEnv === 'integration'
                    ? 'Modo de prueba - Tarjeta de crédito o débito'
                    : 'Paga con tarjeta de crédito o débito',
            ];
        }

        // Verificar Mercado Pago
        if (config('services.mercadopago.public_key') && config('services.mercadopago.access_token')) {
            $gateways[] = [
                'id' => 'mercadopago',
                'name' => 'Mercado Pago',
                'description' => 'Paga con múltiples métodos',
            ];
        }

        return response()->json([
            'gateways' => $gateways,
            'count' => count($gateways),
        ]);
    }
}
