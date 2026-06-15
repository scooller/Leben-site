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
    // Documentación rápida de la API
    Route::get('/', function () {
        return response()->json([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'iLeben API',
                'version' => 'v1',
                'description' => 'Documentación base de endpoints para integración frontend y clientes externos.',
            ],
            'servers' => [
                ['url' => url('/api/v1')],
            ],
            'tags' => [
                ['name' => 'Config', 'description' => 'Configuración pública del sitio'],
                ['name' => 'Auth', 'description' => 'Autenticación de usuarios'],
                ['name' => 'login', 'description' => 'Login de usuario'],
                ['name' => 'register', 'description' => 'Registro de usuario'],
                ['name' => 'Proyectos', 'description' => 'Catálogo de proyectos'],
                ['name' => 'Plantas', 'description' => 'Catálogo de plantas'],
                ['name' => 'Reservas', 'description' => 'Reservas de plantas'],
                ['name' => 'Pagos', 'description' => 'Operaciones de pago'],
                ['name' => 'Checkout', 'description' => 'Flujo de checkout'],
            ],
            'paths' => [
                '/site-config' => ['get' => ['tags' => ['Config'], 'operationId' => 'getSiteConfig', 'summary' => 'Configuración pública del sitio', 'security' => [], 'responses' => ['200' => ['description' => 'Configuración del sitio']]]],
                '/contact-submissions' => ['post' => ['tags' => ['Config'], 'operationId' => 'storeContactSubmission', 'summary' => 'Enviar formulario de contacto', 'security' => [], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object']]]], 'responses' => ['201' => ['description' => 'Contacto recibido'], '422' => ['description' => 'Error de validación']]]],
                '/login' => ['post' => ['tags' => ['Auth'], 'operationId' => 'login', 'summary' => 'Login de usuario', 'security' => [], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object']]]], 'responses' => ['200' => ['description' => 'Autenticado'], '422' => ['description' => 'Error de validación']]]],
                '/register' => ['post' => ['tags' => ['Auth'], 'operationId' => 'register', 'summary' => 'Registro de usuario', 'security' => [], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object']]]], 'responses' => ['201' => ['description' => 'Usuario creado'], '422' => ['description' => 'Error de validación']]]],
                '/proyectos' => ['get' => ['tags' => ['Proyectos'], 'operationId' => 'listProyectos', 'summary' => 'Listado de proyectos', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Listado paginado'], '401' => ['description' => 'No autenticado']]]],
                '/proyectos/{id}' => ['get' => ['tags' => ['Proyectos'], 'operationId' => 'getProyecto', 'summary' => 'Detalle de proyecto', 'security' => [['bearerAuth' => []]], 'parameters' => [['$ref' => '#/components/parameters/Id'], ['name' => 'include_plantas', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean'], 'description' => 'Incluir plantas asociadas al proyecto'], ['name' => 'include_asesores', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean'], 'description' => 'Incluir asesores activos asociados al proyecto'], ['name' => 'campos', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Selección de campos (campos=id,name,direccion,... o fields=...)']], 'responses' => ['200' => ['description' => 'Detalle de proyecto (con plantas y/o asesores si se solicitan)'], '401' => ['description' => 'No autenticado'], '404' => ['description' => 'No encontrado']]]],
                '/plantas' => ['get' => ['tags' => ['Plantas'], 'operationId' => 'listPlantas', 'summary' => 'Listado de plantas', 'security' => [['bearerAuth' => []]], 'parameters' => [['name' => 'proyecto_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer'], 'description' => 'ID de proyecto (alias: project_id)'], ['name' => 'salesforce_proyecto_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Salesforce ID del proyecto'], ['name' => 'project_slug', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Slug del proyecto (alias: slug)'], ['name' => 'programa', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Filtro dormitorios (ej: 2D)'], ['name' => 'piso', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Filtro piso'], ['name' => 'orientacion', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Filtro orientación'], ['name' => 'disponible', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean'], 'description' => 'Solo plantas disponibles (alias: available)'], ['name' => 'region', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Filtro región del proyecto'], ['name' => 'comuna', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Filtro comuna del proyecto'], ['name' => 'evento_sale', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean'], 'description' => 'Filtro evento sale']], 'responses' => ['200' => ['description' => 'Listado paginado'], '401' => ['description' => 'No autenticado']]]],
                '/plantas/filtros-ubicacion' => ['get' => ['tags' => ['Plantas'], 'operationId' => 'getPlantasFiltrosUbicacion', 'summary' => 'Catálogo de regiones y comunas disponibles', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Catálogo de filtros de ubicación'], '401' => ['description' => 'No autenticado']]]],
                '/plantas/{id}' => ['get' => ['tags' => ['Plantas'], 'operationId' => 'getPlanta', 'summary' => 'Detalle de planta', 'security' => [['bearerAuth' => []]], 'parameters' => [['$ref' => '#/components/parameters/Id']], 'responses' => ['200' => ['description' => 'Detalle de planta'], '401' => ['description' => 'No autenticado'], '404' => ['description' => 'No encontrado']]]],
                '/payment-gateways' => ['get' => ['tags' => ['Pagos'], 'operationId' => 'listPaymentGateways', 'summary' => 'Pasarelas de pago disponibles', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Listado de pasarelas'], '401' => ['description' => 'No autenticado']]]],
                '/reservations/planta/{plantId}' => ['get' => ['tags' => ['Reservas'], 'operationId' => 'getPlantReservationStatus', 'summary' => 'Estado de reserva de planta', 'security' => [['bearerAuth' => []]], 'parameters' => [['$ref' => '#/components/parameters/PlantId']], 'responses' => ['200' => ['description' => 'Estado de reserva'], '401' => ['description' => 'No autenticado']]]],
                '/me' => ['get' => ['tags' => ['Auth'], 'operationId' => 'getAuthenticatedUser', 'summary' => 'Usuario autenticado', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Usuario autenticado'], '401' => ['description' => 'No autenticado'], '403' => ['description' => 'Origen no autorizado']]]],
                '/logout' => ['post' => ['tags' => ['Auth'], 'operationId' => 'logout', 'summary' => 'Cerrar sesión', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Sesión cerrada'], '401' => ['description' => 'No autenticado']]]],
                '/checkout' => ['post' => ['tags' => ['Checkout'], 'operationId' => 'checkout', 'summary' => 'Iniciar checkout', 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object']]]], 'responses' => ['200' => ['description' => 'Checkout iniciado'], '422' => ['description' => 'Error de validación']]]],
                '/reservations' => ['post' => ['tags' => ['Reservas'], 'operationId' => 'createReservation', 'summary' => 'Crear reserva', 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object']]]], 'responses' => ['201' => ['description' => 'Reserva creada'], '422' => ['description' => 'Error de validación']]]],
                '/reservations/{sessionToken}' => ['delete' => ['tags' => ['Reservas'], 'operationId' => 'releaseReservation', 'summary' => 'Liberar reserva', 'security' => [['bearerAuth' => []]], 'parameters' => [['$ref' => '#/components/parameters/SessionToken']], 'responses' => ['200' => ['description' => 'Reserva liberada'], '404' => ['description' => 'No encontrada']]]],
                '/payments' => [
                    'get' => ['tags' => ['Pagos'], 'operationId' => 'listPayments', 'summary' => 'Listar pagos', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Listado paginado']]],
                    'post' => ['tags' => ['Pagos'], 'operationId' => 'createPayment', 'summary' => 'Crear pago', 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object']]]], 'responses' => ['201' => ['description' => 'Pago creado'], '422' => ['description' => 'Error de validación']]],
                ],
                '/payments/{id}' => ['get' => ['tags' => ['Pagos'], 'operationId' => 'getPayment', 'summary' => 'Detalle de pago', 'security' => [['bearerAuth' => []]], 'parameters' => [['$ref' => '#/components/parameters/Id']], 'responses' => ['200' => ['description' => 'Detalle de pago'], '404' => ['description' => 'No encontrado']]]],
                '/payments/{id}/manual-proof' => ['post' => ['tags' => ['Pagos'], 'operationId' => 'uploadManualProof', 'summary' => 'Subir comprobante de pago manual', 'security' => [['bearerAuth' => []]], 'parameters' => [['$ref' => '#/components/parameters/Id']], 'requestBody' => ['required' => true, 'content' => ['multipart/form-data' => ['schema' => ['type' => 'object']]]], 'responses' => ['200' => ['description' => 'Comprobante recibido'], '422' => ['description' => 'Error de validación']]]],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Token',
                        'description' => 'Sanctum Bearer token. En rutas protegidas también aplica validación token.origin.',
                    ],
                ],
                'parameters' => [
                    'Id' => [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ],
                    'PlantId' => [
                        'name' => 'plantId',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ],
                    'SessionToken' => [
                        'name' => 'sessionToken',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ],
                ],
            ],
        ]);
    });

    // Configuración del sitio
    Route::get('/site-config', function (Request $request) {
        return response()->json(App\Models\SiteSetting::forFrontend($request));
    });

    // Contacto público
    Route::post('/contact-submissions', [App\Http\Controllers\Api\ContactSubmissionController::class, 'store'])->middleware('throttle:10,1');

    // Autenticación
    Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register'])->middleware('throttle:5,1');

    // Estado publico de pago para pantalla de resultado en frontend
    Route::get('/payments/public-status/{id}', [App\Http\Controllers\Api\PaymentController::class, 'publicStatus']);

    // Catálogo público (el bloqueo por mostrar_plantas se maneja en frontend)
    Route::middleware(['token.origin'])->group(function () {
        Route::get('/proyectos', [App\Http\Controllers\Api\ProyectoController::class, 'index']);
        Route::get('/proyectos/{id}', [App\Http\Controllers\Api\ProyectoController::class, 'show']);

        Route::get('/plantas', [App\Http\Controllers\Api\PlantController::class, 'index']);
        Route::get('/plantas/filtros-ubicacion', [App\Http\Controllers\Api\PlantController::class, 'locationFilters']);
        Route::get('/plantas/proyecto/{projectSlug}/unidad/{unitName}', [App\Http\Controllers\Api\PlantController::class, 'showByProjectSlugAndUnitName']);
        Route::get('/plantas/{id}', [App\Http\Controllers\Api\PlantController::class, 'show']);
    });

    // Endpoints públicos mínimos
});

// Rutas protegidas (requieren autenticación)
Route::prefix('v1')->middleware(['auth:sanctum', 'token.origin'])->group(function () {
    Route::get('/production-sync/export', [App\Http\Controllers\Api\ProductionSyncController::class, 'export']);

    // Usuario autenticado
    Route::get('/me', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout']);

    // Checkout
    Route::post('/checkout', [App\Http\Controllers\Api\CheckoutController::class, 'initiate']);

    // Pasarelas disponibles
    Route::get('/payment-gateways', [App\Http\Controllers\Api\CheckoutController::class, 'availableGateways']);

    // Reservas
    Route::get('/reservations/planta/{plantId}', [App\Http\Controllers\Api\PlantReservationController::class, 'status']);
    Route::post('/reservations', [App\Http\Controllers\Api\PlantReservationController::class, 'reserve']);
    Route::delete('/reservations/{sessionToken}', [App\Http\Controllers\Api\PlantReservationController::class, 'release']);

    // Pagos
    Route::post('/payments', [App\Http\Controllers\Api\PaymentController::class, 'create']);
    Route::get('/payments', [App\Http\Controllers\Api\PaymentController::class, 'index']);
    Route::get('/payments/{id}', [App\Http\Controllers\Api\PaymentController::class, 'show']);
    Route::post('/payments/{id}/manual-proof', [App\Http\Controllers\Api\PaymentController::class, 'uploadManualProof']);
});
