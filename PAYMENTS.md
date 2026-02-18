# Sistema de Pasarelas de Pago

Sistema completo para múltiples pasarelas de pago con Laravel 12 + Filament 5.

## 📦 Instalado

- ✅ **Transbank SDK**: `transbank/transbank-sdk` v2.x
- ✅ **Mercado Pago SDK**: `mercadopago/dx-php` v3.x
- ✅ **PaymentWebhookController**: Manejo de retornos y webhooks
- ✅ **Vistas de resultado**: success, failed, pending

## 🏗️ Arquitectura

```
app/
├── Contracts/PaymentGatewayInterface.php  # Interface común
├── Enums/
│   ├── PaymentGateway.php                 # Enum: transbank|mercadopago|manual
│   └── PaymentStatus.php                  # Enum: pending|completed|failed|etc
├── Facades/PaymentGateway.php             # Facade principal
├── Services/Payment/
│   ├── PaymentGatewayManager.php          # Factory
│   ├── TransbankService.php               # SDK Transbank
│   ├── MercadoPagoService.php             # SDK Mercado Pago
│   └── ManualPaymentService.php           # Pagos manuales
├── Http/Controllers/
│   └── PaymentWebhookController.php       # Retornos y webhooks
└── Models/Payment.php                      # Modelo con enums
```

## 🚀 Uso Rápido

### Crear y procesar pago con Transbank

```php
use App\Models\Payment;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Facades\PaymentGateway as PaymentGatewayFacade;

// 1. Crear registro en BD
$payment = Payment::create([
    'user_id' => auth()->id(),
    'gateway' => PaymentGateway::TRANSBANK,
    'amount' => 10000,
    'currency' => 'CLP',
    'status' => PaymentStatus::PENDING,
    'metadata' => ['buy_order' => 'ORDER-' . time()],
]);

// 2. Iniciar transacción con SDK
$transaction = PaymentGatewayFacade::driver('transbank')->createTransaction([
    'amount' => $payment->amount,
    'buy_order' => $payment->metadata['buy_order'],
    'session_id' => 'session-' . $payment->id,
]);

// 3. Redirigir usuario a Transbank
return redirect($transaction['url'] . '?token_ws=' . $transaction['token']);

// 4. El sistema automáticamente procesa el retorno en:
// POST /payments/transbank/return → PaymentWebhookController@transbankReturn
```

### Crear y procesar pago con Mercado Pago

```php
// 1. Crear registro
$payment = Payment::create([
    'user_id' => auth()->id(),
    'gateway' => PaymentGateway::MERCADOPAGO,
    'amount' => 10000,
    'currency' => 'CLP',
    'status' => PaymentStatus::PENDING,
    'metadata' => ['description' => 'Compra de plantas'],
]);

// 2. Crear preferencia con SDK
$transaction = PaymentGatewayFacade::driver('mercadopago')->createTransaction([
    'amount' => $payment->amount,
    'description' => $payment->metadata['description'],
    'external_reference' => (string) $payment->id,
    'payer_email' => auth()->user()->email,
]);

// 3. Redirigir a Mercado Pago
return redirect($transaction['init_point']);

// 4. MP envía webhook asíncrono a:
// POST /payments/mercadopago/webhook → PaymentWebhookController@mercadopagoWebhook
// Y redirige usuario a:
// GET /payments/mercadopago/return → PaymentWebhookController@mercadopagoReturn
```

## ⚙️ Configuración

### Variables de entorno (.env)

```env
# Transbank Webpay Plus
TRANSBANK_ENABLED=true
TRANSBANK_ENVIRONMENT=integration  # o 'production'
TRANSBANK_COMMERCE_CODE=597055555532
TRANSBANK_API_KEY=579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C

# Mercado Pago
MERCADOPAGO_ENABLED=true
MERCADOPAGO_PUBLIC_KEY=APP_USR-tu-public-key
MERCADOPAGO_ACCESS_TOKEN=APP_USR-tu-access-token

# URLs
APP_URL=https://tu-dominio.com
```

### Webhook de Mercado Pago

Configurar en: https://www.mercadopago.com/developers/panel/notifications/webhooks

**URL:** `https://tu-dominio.com/payments/mercadopago/webhook`

**Eventos:** `payment`

## 🎯 Flujos de Pago Implementados

### Transbank (Webpay Plus)

1. **Crear transacción** → SDK retorna token + URL
2. **Redirigir usuario** → Transbank procesa tarjeta
3. **Retorno POST** → `PaymentWebhookController@transbankReturn`
4. **Confirmar con SDK** → `confirmTransaction($token)`
5. **Actualizar BD** → Estado según `response_code`
6. **Redirigir resultado** → `/payments/success` o `/payments/failed`

**Métodos del SDK:**
- `createTransaction()` - Crear transacción
- `confirmTransaction($token)` - Confirmar después del retorno
- `getTransactionStatus($token)` - Consultar estado
- `refundTransaction($token, $amount)` - Reembolsar

### Mercado Pago

1. **Crear preferencia** → SDK retorna preference_id + init_point
2. **Redirigir usuario** → MP procesa pago
3. **Webhook asíncrono** → `PaymentWebhookController@mercadopagoWebhook`
4. **Consultar con SDK** → `getTransactionStatus($payment_id)`
5. **Actualizar BD** → Mapear estado MP a sistema
6. **Retorno usuario** → `PaymentWebhookController@mercadopagoReturn`

**Métodos del SDK:**
- `createTransaction()` - Crear preferencia
- `confirmTransaction($paymentId)` - Obtener pago
- `getTransactionStatus($paymentId)` - Consultar estado
- `refundTransaction($paymentId, $amount)` - Reembolsar

**Mapeo de estados MP:**
```php
'approved' => PaymentStatus::COMPLETED
'pending' => PaymentStatus::PENDING
'in_process' => PaymentStatus::PROCESSING
'rejected' => PaymentStatus::FAILED
'cancelled' => PaymentStatus::CANCELLED
'refunded' => PaymentStatus::REFUNDED
```

### Pago Manual

Flujo para transferencias bancarias o efectivo:

1. **Crear pago** → Estado: `PENDING_APPROVAL`
2. **Mostrar instrucciones** → Datos bancarios
3. **Usuario envía comprobante** → (implementar upload)
4. **Admin aprueba** → Desde Filament panel
5. **Estado final** → `COMPLETED` o `FAILED`

```php
$service = PaymentGatewayFacade::driver('manual');

// Obtener instrucciones
$instructions = $service->getPaymentInstructions();

// Aprobar manualmente
$service->approvePayment($transactionId, [
    'approved_by' => auth()->id(),
    'notes' => 'Comprobante verificado',
]);
```

## 🔧 Métodos del Facade

```php
use App\Facades\PaymentGateway;

// Driver por defecto (config/payments.php)
PaymentGateway::createTransaction([...]);

// Driver específico
PaymentGateway::driver('transbank')->createTransaction([...]);
PaymentGateway::driver('mercadopago')->createTransaction([...]);
PaymentGateway::driver('manual')->createTransaction([...]);

// Desde un Payment model
PaymentGateway::forPayment($payment)->confirmTransaction($token);

// Listar disponibles
$gateways = PaymentGateway::available(); // ['transbank', 'mercadopago', 'manual']

// Verificar disponibilidad
if (PaymentGateway::isAvailable('transbank')) {
    // ...
}
```

## 📊 Modelo Payment

```php
use App\Models\Payment;

// Crear
$payment = Payment::create([
    'user_id' => 1,
    'gateway' => PaymentGateway::TRANSBANK,
    'amount' => 50000,
    'currency' => 'CLP',
    'status' => PaymentStatus::PENDING,
    'gateway_tx_id' => 'TX-123',
    'metadata' => ['order_id' => 'ORD-456'],
]);

// Scopes
Payment::completed()->get();
Payment::pending()->get();
Payment::failed()->get();
Payment::byGateway(PaymentGateway::TRANSBANK)->get();
Payment::byUser($userId)->get();

// Helpers
$payment->isCompleted();      // bool
$payment->isPending();         // bool
$payment->isFailed();          // bool
$payment->canBeRefunded();     // bool
$payment->canBeApproved();     // bool (manual)

// Actualizar estado
$payment->markAsCompleted();
$payment->markAsFailed('Motivo');
```

## 🎨 Uso en Filament

```php
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;

// Forms
Select::make('gateway')
    ->options(PaymentGateway::toSelectArray())
    ->required(),

Select::make('status')
    ->options(PaymentStatus::toSelectArray())
    ->required(),

// Tables
TextColumn::make('gateway')
    ->formatStateUsing(fn ($state) => $state->label())
    ->icon(fn ($state) => $state->icon()),

TextColumn::make('status')
    ->badge()
    ->color(fn ($state) => $state->color())
    ->formatStateUsing(fn ($state) => $state->label()),
```

## 🧪 Testing

### Tarjetas de prueba Transbank (Integración)

- **Débito exitoso:** 4051 8856 0000 0002 (CVV: 123, cualquier fecha futura)
- **Crédito exitoso:** 4051 8860 0000 0001 (CVV: 123, cualquier fecha futura)
- **Rechazada:** 4051 8842 3993 7763

### Credenciales Mercado Pago (Test)

1. Ir a: https://www.mercadopago.com/developers/panel/app
2. Crear aplicación
3. Copiar credenciales de **Test**

## 🔍 Consultas y Reembolsos

### Consultar estado

```php
// Transbank (usa token)
$status = PaymentGateway::driver('transbank')
    ->getTransactionStatus($token);

// Mercado Pago (usa payment_id)
$status = PaymentGateway::driver('mercadopago')
    ->getTransactionStatus($paymentId);
```

### Reembolsar pago

```php
// Reembolso total
$refund = PaymentGateway::driver('transbank')
    ->refundTransaction($token);

// Reembolso parcial
$refund = PaymentGateway::driver('mercadopago')
    ->refundTransaction($paymentId, 5000);
```

## 🛣️ Rutas Configuradas

```php
// Webhooks y retornos
POST   /payments/transbank/return       # Retorno Transbank
POST   /payments/mercadopago/webhook    # Webhook Mercado Pago
GET    /payments/mercadopago/return     # Retorno Mercado Pago

// Páginas de resultado
GET    /payments/success/{payment?}     # Pago exitoso
GET    /payments/failed/{payment?}      # Pago rechazado
GET    /payments/pending/{payment?}     # Pago pendiente
```

## 📝 Logs

Todos los servicios registran información detallada en `storage/logs/laravel.log`:

```bash
# Ver logs en tiempo real
tail -f storage/logs/laravel.log | grep -E "Transbank|MercadoPago"
```

**Eventos registrados:**
- Creación de transacciones
- Confirmaciones y retornos
- Webhooks recibidos
- Errores con contexto completo
- Reembolsos procesados

## 🚨 Errores Comunes

### Transbank: "Invalid commerce code"
**Causa:** Environment en producción pero usando credenciales de integración  
**Solución:** `TRANSBANK_ENVIRONMENT=integration`

### Transbank: "Transaction not found"
**Causa:** Token expiró (15 minutos)  
**Solución:** Crear nueva transacción

### Mercado Pago: "Invalid access token"
**Causa:** Token de test en producción o viceversa  
**Solución:** Verificar ambiente del token

### Webhook no se recibe (MP)
**Causa:** URL no accesible públicamente  
**Solución:** Usar ngrok para desarrollo local

## 📚 Recursos

- [SDK Transbank](https://github.com/TransbankDevelopers/transbank-sdk-php)
- [SDK Mercado Pago](https://www.mercadopago.com/developers/es/docs/sdks-library/server-side)
- [Panel Transbank](https://www.transbankdevelopers.cl/)
- [Panel Mercado Pago](https://www.mercadopago.com/developers/panel)

## ✅ Checklist

- [x] SDKs instalados
- [x] Services con SDKs reales
- [x] PaymentWebhookController
- [x] Rutas configuradas
- [x] Vistas de resultado
- [ ] Variables .env configuradas
- [ ] Webhook MP registrado
- [ ] Pruebas con tarjetas test
- [ ] Validación firma webhooks MP
- [ ] Notificaciones email
- [ ] Deploy producción

## 🎯 Opcional (No implementado)

- Jobs asíncronos para procesamiento de pagos
- Notificaciones por email
- Filament Actions para aprobar pagos manuales
- Testing automatizado
- Rate limiting en webhooks
- Monitoring y alertas
