<?php

use App\Http\Controllers\AdvisorWhatsappRedirectController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ShortLinkRedirectController;
use App\Models\Payment;
use Illuminate\Support\Facades\Route;

// Rutas de preview para frontend (deben saltar mantenimiento)
Route::get('/frontend-preview/{token}', function ($token) {
    // Aquí deberías tener la lógica real de preview, placeholder por ahora
    return response()->json(['preview' => true, 'token' => $token]);
})->middleware('bypass.maintenance.preview');

Route::get('/preview-link/{token}', function ($token) {
    // Aquí deberías tener la lógica real de preview, placeholder por ahora
    return response()->json(['preview' => true, 'token' => $token]);
})->middleware('bypass.maintenance.preview');

Route::get('/', function () {
    return redirect('/admin');
});

// Servir archivos del almacenamiento público bajo la ruta /curator/ (para compatibilidad con Curator)
Route::get('/curator/{path}', function (string $path) {
    $fullPath = storage_path('app/public/' . $path);

    if (! file_exists($fullPath)) {
        abort(404);
    }

    return response()->file($fullPath);
})->where('path', '.*');

// Rutas públicas para acortador
Route::get('/s/{slug}', ShortLinkRedirectController::class)
    ->middleware('throttle:120,1')
    ->name('short-links.redirect');

Route::get('/go/asesores/{asesor}/whatsapp', AdvisorWhatsappRedirectController::class)
    ->middleware('throttle:120,1')
    ->name('advisors.whatsapp.redirect');

// Rutas de webhooks y retornos de pasarelas de pago
Route::prefix('payments')->name('payment.')->group(function () {
    // Transbank - Página puente para enviar POST token_ws al endpoint de Webpay
    Route::get('transbank/redirect', [PaymentWebhookController::class, 'transbankRedirect'])
        ->name('transbank.redirect');

    // Transbank - Aceptar GET y POST (GET del navegador, POST de confirmación)
    Route::match(['get', 'post'], 'transbank/return', [PaymentWebhookController::class, 'transbankReturn'])
        ->name('transbank.return');

    // Mercado Pago - Webhook para notificaciones IPN
    Route::post('mercadopago/webhook', [PaymentWebhookController::class, 'mercadopagoWebhook'])
        ->name('mercadopago.webhook');

    // Mercado Pago - Retorno GET cuando el usuario vuelve
    Route::get('mercadopago/return', [PaymentWebhookController::class, 'mercadopagoReturn'])
        ->name('mercadopago.return');

    // Páginas de resultado
    Route::get('success/{payment?}', function ($payment = null) {
        $paymentModel = null;

        if ($payment !== null) {
            $paymentModel = Payment::query()->find($payment);
        }

        $shouldTrackCheckoutSuccess = ! ($paymentModel?->requiresManualApproval() ?? false);

        return view('payments.success', [
            'payment' => $payment,
            'shouldTrackCheckoutSuccess' => $shouldTrackCheckoutSuccess,
        ]);
    })->name('success');

    Route::get('failed/{payment?}', function ($payment = null) {
        return view('payments.failed', compact('payment'));
    })->name('failed');

    Route::get('pending/{payment?}', function ($payment = null) {
        return view('payments.pending', compact('payment'));
    })->name('pending');
});

// Rutas de integración Salesforce OAuth
Route::prefix('salesforce')->name('salesforce.')->group(function () {
    Route::get('oauth/connect', [\App\Http\Controllers\SalesforceOAuthController::class, 'connect'])
        ->middleware('auth')
        ->name('oauth.connect');

    Route::get('callback', [\App\Http\Controllers\SalesforceOAuthController::class, 'callback'])
        ->name('callback');
});
