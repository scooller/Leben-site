<?php

use App\Http\Controllers\AdvisorWhatsappRedirectController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ShortLinkRedirectController;
use App\Models\Payment;
use App\Models\Plant;
use App\Models\SiteSetting;
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

Route::get('/sitemap.xml', function () {
    $settings = SiteSetting::current();
    $baseUrl = rtrim((string) ($settings->site_url ?: config('app.frontend_url', 'https://sale.ileben.cl')), '/');

    $staticUrls = collect([
        [
            'loc' => $baseUrl . '/',
            'changefreq' => 'daily',
            'priority' => '1.0',
            'lastmod' => now()->toDateString(),
        ],
        [
            'loc' => $baseUrl . '/plantas',
            'changefreq' => 'daily',
            'priority' => '0.9',
            'lastmod' => now()->toDateString(),
        ],
        [
            'loc' => $baseUrl . '/f',
            'changefreq' => 'daily',
            'priority' => '0.8',
            'lastmod' => now()->toDateString(),
        ],
    ]);

    $plantUrls = collect();

    if ((bool) ($settings->mostrar_plantas ?? true)) {
        $plantUrls = Plant::query()
            ->with(['proyecto:id,salesforce_id,slug,name,is_active'])
            ->where('is_active', true)
            ->whereHas('proyecto', fn($query) => $query->where('is_active', true))
            ->whereDoesntHave('activeReservation')
            ->whereDoesntHave('completedReservation')
            ->whereDoesntHave('completedPayment')
            ->get()
            ->map(function (Plant $plant) use ($baseUrl): ?array {
                $projectSlug = trim((string) ($plant->proyecto?->slug ?: ''));
                $unitName = trim((string) ($plant->name ?? ''));

                if ($projectSlug === '' || $unitName === '') {
                    return null;
                }

                return [
                    'loc' => $baseUrl . '/p/' . rawurlencode($projectSlug) . '/' . rawurlencode($unitName),
                    'changefreq' => 'daily',
                    'priority' => '0.7',
                    'lastmod' => optional($plant->updated_at)->toDateString() ?? now()->toDateString(),
                ];
            })
            ->filter();
    }

    $urls = $staticUrls->merge($plantUrls)->values();

    return response()
        ->view('sitemap.xml', ['urls' => $urls])
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap.xml');

Route::get('/robots.txt', function () {
    $settings = SiteSetting::current();
    $baseUrl = rtrim((string) ($settings->site_url ?: config('app.frontend_url', url('/'))), '/');

    $content = implode("\n", [
        'User-agent: *',
        'Allow: /',
        'Disallow: /frontend-preview/',
        'Disallow: /preview-link/',
        'Disallow: /*preview_token=',
        'Content-Signal: ai-train=no, search=yes, ai-input=no',
        '',
        "Sitemap: {$baseUrl}/sitemap.xml",
        "Agentmap: {$baseUrl}/.well-known/ai-catalog.json",
        '',
    ]);

    return response($content, 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->name('robots.txt');

Route::match(['GET', 'HEAD'], '/.well-known/api-catalog', function () {
    $settings = SiteSetting::current();
    $baseUrl = rtrim((string) ($settings->site_url ?: config('app.frontend_url', url('/'))), '/');
    $apiBaseUrl = rtrim(url('/api/v1'), '/');

    $catalog = [
        'linkset' => [
            [
                'anchor' => $baseUrl . '/',
                'profile' => 'https://www.rfc-editor.org/rfc/rfc9727',
                'author' => (string) ($settings->site_name ?: 'iLeben'),
                'item' => [
                    [
                        'href' => $apiBaseUrl,
                        'rel' => 'service-desc',
                        'type' => 'application/json',
                        'title' => 'iLeben OpenAPI v1 Specification',
                    ],
                    [
                        'href' => $apiBaseUrl,
                        'rel' => 'service-doc',
                        'type' => 'application/json',
                        'title' => 'iLeben API Documentation',
                    ],
                ],
            ],
        ],
    ];

    return response()
        ->json($catalog, 200, [
            'Content-Type' => 'application/linkset+json; charset=UTF-8',
            'Link' => '</.well-known/api-catalog>; rel="api-catalog"',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        ]);
})->name('well-known.api-catalog');

Route::get('/llms.txt', function (\App\Services\Agent\MarkdownRepresentationService $markdownService) {
    return $markdownService->makeResponse($markdownService->renderHomepageMarkdown());
})->name('llms.txt');

Route::get('/.well-known/llms.txt', function (\App\Services\Agent\MarkdownRepresentationService $markdownService) {
    return $markdownService->makeResponse($markdownService->renderHomepageMarkdown());
});

Route::get('/', function () {
    return redirect('/admin');
});

// Servir archivos del almacenamiento público bajo la ruta /curator/ (para compatibilidad con Curator)
Route::get('/curator/{path}', function (string $path) {
    $basePath = realpath(storage_path('app/public'));
    $fullPath = realpath(storage_path('app/public/' . $path));

    if (! $fullPath || ! $basePath || ! str_starts_with($fullPath, $basePath . DIRECTORY_SEPARATOR) || ! is_file($fullPath)) {
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

// ──────────────────────────────────────────────────────────
// Agent Commerce Protocol Discovery Endpoints
// ──────────────────────────────────────────────────────────

/**
 * ACP — Agentic Commerce Protocol discovery document.
 * Spec: https://agenticcommerce.dev
 * Required fields: protocol.name, protocol.version, api_base_url, transports, capabilities.services
 */
Route::match(['GET', 'HEAD'], '/.well-known/acp.json', function () {
    $apiBaseUrl = rtrim(url('/api/v1'), '/');

    return response()->json([
        'protocol' => [
            'name'    => 'acp',
            'version' => '1.0',
        ],
        'api_base_url' => $apiBaseUrl,
        'transports'   => ['http'],
        'capabilities' => [
            'services' => [
                'real-estate-catalog',
                'property-listings',
                'contact-submissions',
                'reservations',
                'payments',
            ],
        ],
        'description' => 'iLeben real-estate project and unit catalog API with checkout and reservation flows.',
        'contact'     => [
            'url' => url('/api/v1'),
        ],
    ], 200, [
        'Content-Type'                 => 'application/json; charset=UTF-8',
        'Access-Control-Allow-Origin'  => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Cache-Control'                => 'public, max-age=3600',
    ]);
})->name('well-known.acp');

/**
 * UCP — Universal Commerce Protocol discovery document.
 * Spec: https://ucp.dev/specification/overview/
 * Required fields: protocol_version, services, capabilities, endpoints
 */
Route::match(['GET', 'HEAD'], '/.well-known/ucp', function () {
    $apiBaseUrl = rtrim(url('/api/v1'), '/');

    return response()->json([
        'protocol_version' => '1.0',
        'services'         => [
            [
                'id'          => 'real-estate-catalog',
                'name'        => 'iLeben Property Catalog',
                'description' => 'Browse and filter real-estate projects and floor plans available for sale in Chile.',
                'type'        => 'catalog',
                'url'         => $apiBaseUrl . '/proyectos',
            ],
            [
                'id'          => 'reservations',
                'name'        => 'Unit Reservation',
                'description' => 'Reserve a specific housing unit (planta) temporarily.',
                'type'        => 'reservation',
                'url'         => $apiBaseUrl . '/reservations',
            ],
            [
                'id'          => 'checkout',
                'name'        => 'Payment Checkout',
                'description' => 'Initiate a payment transaction via Transbank Webpay or Mercado Pago.',
                'type'        => 'checkout',
                'url'         => $apiBaseUrl . '/checkout',
            ],
        ],
        'capabilities' => [
            'payment_methods' => ['transbank', 'mercadopago', 'card'],
            'currencies'      => ['CLP'],
            'reservations'    => true,
            'catalog'         => true,
            'checkout'        => true,
        ],
        'endpoints' => [
            'catalog'      => $apiBaseUrl . '/proyectos',
            'units'        => $apiBaseUrl . '/plantas',
            'reservations' => $apiBaseUrl . '/reservations',
            'checkout'     => $apiBaseUrl . '/checkout',
            'payments'     => $apiBaseUrl . '/payments',
            'openapi'      => url('/openapi.json'),
        ],
        'spec_url' => 'https://ucp.dev/specification/overview/',
    ], 200, [
        'Content-Type'                 => 'application/json; charset=UTF-8',
        'Access-Control-Allow-Origin'  => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Cache-Control'                => 'public, max-age=3600',
    ]);
})->name('well-known.ucp');

/**
 * MPP — Machine Payment Protocol: OpenAPI document with x-payment-info extensions.
 * Spec: https://mpp.dev / https://paymentauth.org/draft-payment-discovery-00.txt
 * Agents use this to discover which operations require payment and how to pay.
 * Payment methods: Transbank / Mercado Pago map to MPP "card" method.
 */
Route::match(['GET', 'HEAD'], '/openapi.json', function () {
    $apiBase  = rtrim(url('/api/v1'), '/');
    $siteBase = rtrim(url('/'), '/');

    $paymentInfo = [
        'intent'      => 'charge',
        'method'      => 'card',
        'amount'      => 0,
        'currency'    => 'CLP',
        'description' => 'Access via Transbank Webpay or Mercado Pago',
        'payment_url' => $apiBase . '/checkout',
    ];

    return response()->json([
        'openapi' => '3.1.0',
        'info'    => [
            'title'       => 'iLeben API',
            'version'     => 'v1',
            'description' => 'Real-estate project and unit catalog with checkout, reservations, and payment flows. ' .
                             'Supports Transbank Webpay Plus and Mercado Pago.',
            'contact' => ['url' => $siteBase],
            'x-service-info' => [
                'categories'   => ['real-estate', 'catalog', 'reservations', 'payments'],
                'payment_page' => $apiBase . '/checkout',
            ],
        ],
        'servers' => [
            ['url' => $apiBase, 'description' => 'iLeben API v1'],
        ],
        'paths' => [
            '/proyectos' => [
                'get' => [
                    'operationId'   => 'listProyectos',
                    'summary'       => 'List real-estate projects',
                    'description'   => 'Returns paginated projects with precio_desde and tipologias.',
                    'tags'          => ['Catalog'],
                    'x-payment-info' => $paymentInfo,
                    'responses'     => [
                        '200' => ['description' => 'Paginated project list'],
                        '401' => ['description' => 'Unauthorized'],
                    ],
                ],
            ],
            '/proyectos/{id}' => [
                'get' => [
                    'operationId'   => 'getProyecto',
                    'summary'       => 'Get project detail',
                    'tags'          => ['Catalog'],
                    'x-payment-info' => $paymentInfo,
                    'parameters'    => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Project detail'],
                        '404' => ['description' => 'Not found'],
                    ],
                ],
            ],
            '/plantas' => [
                'get' => [
                    'operationId'   => 'listPlantas',
                    'summary'       => 'List housing units (floor plans)',
                    'tags'          => ['Catalog'],
                    'x-payment-info' => $paymentInfo,
                    'responses'     => [
                        '200' => ['description' => 'Paginated unit list'],
                        '401' => ['description' => 'Unauthorized'],
                    ],
                ],
            ],
            '/plantas/{id}' => [
                'get' => [
                    'operationId'   => 'getPlanta',
                    'summary'       => 'Get unit detail',
                    'tags'          => ['Catalog'],
                    'x-payment-info' => $paymentInfo,
                    'parameters'    => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Unit detail'],
                        '404' => ['description' => 'Not found'],
                    ],
                ],
            ],
            '/checkout' => [
                'post' => [
                    'operationId'   => 'initiateCheckout',
                    'summary'       => 'Initiate payment checkout',
                    'description'   => 'Start a Transbank Webpay or Mercado Pago payment session for a unit reservation.',
                    'tags'          => ['Payments'],
                    'x-payment-info' => array_merge($paymentInfo, [
                        'intent'      => 'session',
                        'description' => 'Initiates a payment session via Transbank or Mercado Pago',
                    ]),
                    'requestBody' => [
                        'required' => true,
                        'content'  => ['application/json' => ['schema' => ['type' => 'object']]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Checkout session initiated'],
                        '422' => ['description' => 'Validation error'],
                    ],
                ],
            ],
            '/reservations' => [
                'post' => [
                    'operationId' => 'createReservation',
                    'summary'     => 'Reserve a housing unit',
                    'tags'        => ['Reservations'],
                    'requestBody' => [
                        'required' => true,
                        'content'  => ['application/json' => ['schema' => ['type' => 'object']]],
                    ],
                    'responses' => [
                        '201' => ['description' => 'Reservation created'],
                        '422' => ['description' => 'Validation error'],
                    ],
                ],
            ],
            '/contact-submissions' => [
                'post' => [
                    'operationId' => 'submitContact',
                    'summary'     => 'Submit a contact / lead inquiry',
                    'tags'        => ['Contact'],
                    'requestBody' => [
                        'required' => true,
                        'content'  => ['application/json' => ['schema' => ['type' => 'object']]],
                    ],
                    'responses' => [
                        '201' => ['description' => 'Submission received'],
                        '422' => ['description' => 'Validation error'],
                    ],
                ],
            ],
        ],
        'components' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type'        => 'http',
                    'scheme'      => 'bearer',
                    'bearerFormat' => 'Token',
                ],
            ],
        ],
    ], 200, [
        'Content-Type'                 => 'application/json; charset=UTF-8',
        'Access-Control-Allow-Origin'  => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Cache-Control'                => 'public, max-age=3600',
    ]);
})->name('openapi.json');

/**
 * Auth.md — Agent Authentication and Registration Specification
 * Spec: https://workos.com/auth-md
 */
Route::get('/auth.md', function () {
    $settings = SiteSetting::current();
    $baseUrl = rtrim((string) ($settings->site_url ?: config('app.frontend_url', url('/'))), '/');

    $markdown = implode("\n", [
        '# Auth.md - iLeben Agent Authentication and Registration',
        '',
        'Este documento define el protocolo y directrices de autenticación y registro para Agentes de Inteligencia Artificial (AI Agents) que interactúan con la plataforma iLeben.',
        '',
        '## Información General',
        "- **Audiencia**: Agentes de IA autónomos, asistentes LLM, sistemas multiagente y servicios de integración comercial.",
        "- **Servidor de Autorización OAuth 2.0**: `{$baseUrl}/.well-known/oauth-authorization-server`",
        "- **Metadatos de Recursos Protegidos (RFC 9728)**: `{$baseUrl}/.well-known/oauth-protected-resource`",
        "- **OpenID Connect Discovery**: `{$baseUrl}/.well-known/openid-configuration`",
        "- **Documentación API / OpenAPI**: `{$baseUrl}/openapi.json`",
        '',
        '## Registro Dinámico de Agentes (Agent Provisioning)',
        'Los agentes de IA pueden registrarse o solicitar credenciales de acceso a través del endpoint de registro:',
        "- **Endpoint de Registro**: `POST {$baseUrl}/agent/register`",
        '- **Content-Type**: `application/json`',
        '',
        '### Tipos de Identidad Soportados',
        '1. `anonymous`: Agentes en modo consulta y navegación de proyectos sin sesión de usuario final.',
        '2. `identity_assertion`: Agentes que representan a un usuario verificado (ej. `verified_email` o `urn:ietf:params:oauth:token-type:id-jag`).',
        '',
        '### Tipos de Credenciales Soportadas',
        '- `bearer_token`: Tokens de acceso Bearer temporales.',
        '- `api_key`: Clave API para comunicación directa entre agentes y servicios.',
        '',
        '## Uso de Credenciales en Peticiones',
        'Todas las peticiones a endpoints protegidos deben incluir la credencial en la cabecera HTTP estándar:',
        '```http',
        'Authorization: Bearer <access_token>',
        '```',
        '',
        '## Alcances Disponibles (Scopes)',
        '- `read`: Acceso de lectura general.',
        '- `write`: Acceso de escritura general.',
        '- `catalog:read`: Consulta del catálogo de proyectos inmobiliarios y departamentos (plantas).',
        '- `reservations:write`: Creación y seguimiento de reservas de unidades.',
        '- `payments:write`: Inicio y consulta de transacciones de pago.',
        '',
        '## Flujos de Reclamo y Revocación (Claim & Revocation)',
        "- **Endpoint de Reclamo de Identidad**: `POST {$baseUrl}/agent/claim`",
        "- **Endpoint de Revocación de Tokens**: `POST {$baseUrl}/oauth/revoke`",
        '',
    ]);

    return response($markdown, 200, [
        'Content-Type' => 'text/markdown; charset=UTF-8',
        'Vary' => 'Accept',
        'x-markdown-tokens' => '250',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
    ]);
})->name('auth.md');

/**
 * OAuth 2.0 Authorization Server Discovery (RFC 8414 & Auth.md)
 */
Route::match(['GET', 'HEAD'], '/.well-known/oauth-authorization-server', function () {
    $settings = SiteSetting::current();
    $baseUrl = rtrim((string) ($settings->site_url ?: config('app.frontend_url', url('/'))), '/');

    return response()->json([
        'issuer' => $baseUrl,
        'authorization_endpoint' => $baseUrl . '/oauth/authorize',
        'token_endpoint' => $baseUrl . '/oauth/token',
        'registration_endpoint' => $baseUrl . '/agent/register',
        'jwks_uri' => $baseUrl . '/.well-known/jwks.json',
        'response_types_supported' => ['code', 'token'],
        'grant_types_supported' => [
            'authorization_code',
            'client_credentials',
            'refresh_token',
            'urn:ietf:params:oauth:grant-type:token-exchange',
        ],
        'token_endpoint_auth_methods_supported' => [
            'client_secret_basic',
            'client_secret_post',
            'none',
        ],
        'scopes_supported' => [
            'catalog:read',
            'reservations:write',
            'payments:write',
            'read',
            'write',
        ],
        'agent_auth' => [
            'skill' => $baseUrl . '/.well-known/agent-skills/auth-md/SKILL.md',
            'register_uri' => $baseUrl . '/agent/register',
            'supported_identity_types' => ['anonymous', 'identity_assertion'],
            'identity_types_supported' => ['anonymous', 'identity_assertion'],
            'identity_assertion' => [
                'assertion_types_supported' => [
                    'urn:ietf:params:oauth:token-type:id-jag',
                    'verified_email',
                ],
                'credential_types_supported' => ['bearer_token', 'api_key'],
                'claim_uri' => $baseUrl . '/agent/claim',
            ],
            'anonymous' => [
                'credential_types_supported' => ['bearer_token'],
                'claim_uri' => $baseUrl . '/agent/claim',
            ],
        ],
    ], 200, [
        'Content-Type' => 'application/json; charset=UTF-8',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Cache-Control' => 'public, max-age=3600',
    ]);
});

/**
 * OpenID Connect Discovery
 */
Route::match(['GET', 'HEAD'], '/.well-known/openid-configuration', function () {
    $settings = SiteSetting::current();
    $baseUrl = rtrim((string) ($settings->site_url ?: config('app.frontend_url', url('/'))), '/');

    return response()->json([
        'issuer' => $baseUrl,
        'authorization_endpoint' => $baseUrl . '/oauth/authorize',
        'token_endpoint' => $baseUrl . '/oauth/token',
        'userinfo_endpoint' => $baseUrl . '/api/v1/user',
        'jwks_uri' => $baseUrl . '/.well-known/jwks.json',
        'registration_endpoint' => $baseUrl . '/agent/register',
        'scopes_supported' => [
            'openid',
            'profile',
            'email',
            'catalog:read',
            'reservations:write',
            'payments:write',
        ],
        'response_types_supported' => ['code', 'token', 'id_token'],
        'grant_types_supported' => [
            'authorization_code',
            'client_credentials',
            'refresh_token',
        ],
        'subject_types_supported' => ['public'],
        'id_token_signing_alg_values_supported' => ['RS256'],
    ], 200, [
        'Content-Type' => 'application/json; charset=UTF-8',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Cache-Control' => 'public, max-age=3600',
    ]);
});

/**
 * OAuth Protected Resource Metadata (RFC 9728)
 */
Route::match(['GET', 'HEAD'], '/.well-known/oauth-protected-resource', function () {
    $settings = SiteSetting::current();
    $baseUrl = rtrim((string) ($settings->site_url ?: config('app.frontend_url', url('/'))), '/');

    return response()->json([
        'resource' => $baseUrl,
        'authorization_servers' => [$baseUrl],
        'scopes_supported' => [
            'catalog:read',
            'reservations:write',
            'payments:write',
            'read',
            'write',
        ],
        'bearer_methods_supported' => ['header'],
        'resource_documentation' => $baseUrl . '/openapi.json',
    ], 200, [
        'Content-Type' => 'application/json; charset=UTF-8',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Cache-Control' => 'public, max-age=3600',
    ]);
});

/**
 * MCP Server Card (SEP-1649)
 */
Route::match(['GET', 'HEAD'], '/.well-known/mcp/server-card.json', function () {
    $settings = SiteSetting::current();
    $baseUrl = rtrim((string) ($settings->site_url ?: config('app.frontend_url', url('/'))), '/');

    return response()->json([
        '$schema' => 'https://raw.githubusercontent.com/modelcontextprotocol/modelcontextprotocol/main/schema/server-card.json',
        'serverInfo' => [
            'name' => 'ileben-real-estate-mcp',
            'title' => 'iLeben Real Estate MCP Server',
            'version' => '1.0.0',
            'description' => 'Model Context Protocol server for searching real-estate developments, floor plans, and initiating unit reservations in Chile.',
        ],
        'transport' => [
            'type' => 'streamable-http',
            'endpoint' => $baseUrl . '/mcp',
        ],
        'capabilities' => [
            'tools' => [
                'list' => true,
                'call' => true,
            ],
            'resources' => [
                'subscribe' => false,
                'list' => true,
                'read' => true,
            ],
            'prompts' => [
                'list' => true,
                'get' => true,
            ],
        ],
    ], 200, [
        'Content-Type' => 'application/json; charset=UTF-8',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Cache-Control' => 'public, max-age=3600',
    ]);
});

/**
 * Agent Skills Discovery Index (RFC v0.2.0)
 */
Route::match(['GET', 'HEAD'], '/.well-known/agent-skills/index.json', function () {
    $settings = SiteSetting::current();
    $baseUrl = rtrim((string) ($settings->site_url ?: config('app.frontend_url', url('/'))), '/');

    return response()->json([
        '$schema' => 'https://schemas.agentskills.io/discovery/0.2.0/schema.json',
        'skills' => [
            [
                'name' => 'catalog-search',
                'type' => 'skill-md',
                'description' => 'Search and inspect real estate projects, communes, and available housing units in Chile',
                'url' => $baseUrl . '/.well-known/agent-skills/catalog-search/SKILL.md',
                'digest' => 'sha256:20574a739eb3ad45de0792cb49690287dfc4af4ecaefd05bf5d9238c495a7162',
            ],
            [
                'name' => 'unit-reservation',
                'type' => 'skill-md',
                'description' => 'Initiate and track unit reservations for real estate developments',
                'url' => $baseUrl . '/.well-known/agent-skills/unit-reservation/SKILL.md',
                'digest' => 'sha256:e8004a617f90c168b1d44e40791f88176c3b768d0ab5829261652f9ececfca9b',
            ],
            [
                'name' => 'auth-md',
                'type' => 'skill-md',
                'description' => 'Register and authenticate autonomous AI agents with the iLeben API',
                'url' => $baseUrl . '/.well-known/agent-skills/auth-md/SKILL.md',
                'digest' => 'sha256:525d111db91c8b351344d640e04dca1592722318cb88b408cfa94520b2ac94db',
            ],
        ],
    ], 200, [
        'Content-Type' => 'application/json; charset=UTF-8',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Cache-Control' => 'public, max-age=3600',
    ]);
});

Route::get('/.well-known/agent-skills/{skill}/SKILL.md', function (string $skill) {
    $allowed = [
        'catalog-search' => 'frontend/public/.well-known/agent-skills/catalog-search/SKILL.md',
        'unit-reservation' => 'frontend/public/.well-known/agent-skills/unit-reservation/SKILL.md',
        'auth-md' => 'frontend/public/.well-known/agent-skills/auth-md/SKILL.md',
    ];

    if (!isset($allowed[$skill])) {
        abort(404);
    }

    $filePath = base_path($allowed[$skill]);
    if (!file_exists($filePath)) {
        abort(404);
    }

    return response(file_get_contents($filePath), 200, [
        'Content-Type' => 'text/markdown; charset=UTF-8',
        'Vary' => 'Accept',
        'Access-Control-Allow-Origin' => '*',
    ]);
});

/**
 * ARD — Agentic Resource Discovery Manifest
 * Spec: https://agenticresourcediscovery.org
 */
Route::match(['GET', 'HEAD'], '/.well-known/ai-catalog.json', function () {
    $settings = SiteSetting::current();
    $baseUrl = rtrim((string) ($settings->site_url ?: config('app.frontend_url', url('/'))), '/');
    $host = parse_url($baseUrl, PHP_URL_HOST) ?: 'sale.ileben.cl';

    return response()->json([
        'specVersion' => '1.0',
        'host' => [
            'displayName' => 'iLeben Inmobiliaria',
            'identifier' => 'did:web:' . $host,
        ],
        'entries' => [
            [
                'identifier' => 'urn:air:' . $host . ':server:mcp',
                'displayName' => 'iLeben Real Estate MCP Server',
                'type' => 'application/mcp-server-card+json',
                'url' => $baseUrl . '/.well-known/mcp/server-card.json',
                'representativeQueries' => [
                    'buscar proyectos inmobiliarios en santiago y el sur',
                    'consultar planos y tipologias de departamentos disponibles',
                    'ver precios y disponibilidad de departamentos en venta',
                ],
            ],
            [
                'identifier' => 'urn:air:' . $host . ':api:openapi',
                'displayName' => 'iLeben OpenAPI Real Estate Catalog API',
                'type' => 'application/vnd.oai.openapi+json',
                'url' => $baseUrl . '/openapi.json',
                'representativeQueries' => [
                    'listar departamentos disponibles en venta chile',
                    'obtener especificaciones de la api rest de ileben',
                    'iniciar reserva o cotizacion de vivienda',
                ],
            ],
            [
                'identifier' => 'urn:air:' . $host . ':agent:skills',
                'displayName' => 'iLeben Agent Skills Index',
                'type' => 'application/json',
                'url' => $baseUrl . '/.well-known/agent-skills/index.json',
                'representativeQueries' => [
                    'descubrir habilidades del agente inmobiliario ileben',
                    'habilidades para buscar proyectos y reservar departamentos',
                ],
            ],
            [
                'identifier' => 'urn:air:' . $host . ':agent:commerce',
                'displayName' => 'iLeben Agentic Commerce Protocol',
                'type' => 'application/json',
                'url' => $baseUrl . '/.well-known/acp.json',
                'representativeQueries' => [
                    'comprar departamento en chile a traves de agente',
                    'cotizar reserva de departamento inmobiliario',
                ],
            ],
        ],
    ], 200, [
        'Content-Type' => 'application/json; charset=UTF-8',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Cache-Control' => 'public, max-age=3600',
    ]);
});

// ──────────────────────────────────────────────────────────

// Rutas de integración Salesforce OAuth
Route::prefix('salesforce')->name('salesforce.')->group(function () {
    Route::get('oauth/connect', [\App\Http\Controllers\SalesforceOAuthController::class, 'connect'])
        ->middleware('auth')
        ->name('oauth.connect');

    Route::get('callback', [\App\Http\Controllers\SalesforceOAuthController::class, 'callback'])
        ->name('callback');
});
