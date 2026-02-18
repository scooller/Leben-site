<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas públicas
Route::prefix('v1')->group(function () {
    // Configuración del sitio
    Route::get('/site-config', function () {
        return response()->json(App\Models\SiteSetting::forFrontend());
    });

    // Autenticación
    Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register']);

    // Proyectos públicos
    Route::get('/proyectos', [App\Http\Controllers\Api\ProyectoController::class, 'index']);
    Route::get('/proyectos/{id}', [App\Http\Controllers\Api\ProyectoController::class, 'show']);

    // Plantas disponibles
    Route::get('/plants', [App\Http\Controllers\Api\PlantController::class, 'index']);
    Route::get('/plants/{id}', [App\Http\Controllers\Api\PlantController::class, 'show']);

    // Checkout y pasarelas
    Route::post('/checkout', [App\Http\Controllers\Api\CheckoutController::class, 'initiate']);
    Route::get('/payment-gateways', [App\Http\Controllers\Api\CheckoutController::class, 'availableGateways']);
});

// Rutas protegidas (requieren autenticación)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Usuario autenticado
    Route::get('/me', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout']);

    // Pagos
    Route::post('/payments', [App\Http\Controllers\Api\PaymentController::class, 'create']);
    Route::get('/payments', [App\Http\Controllers\Api\PaymentController::class, 'index']);
    Route::get('/payments/{id}', [App\Http\Controllers\Api\PaymentController::class, 'show']);
});
