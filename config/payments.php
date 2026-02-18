<?php

use App\Enums\PaymentGateway;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway that will be used by
    | your application when no gateway is specified explicitly.
    |
    */

    'default' => env('PAYMENT_DEFAULT_GATEWAY', PaymentGateway::TRANSBANK->value),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the payment gateways used by your
    | application. Each gateway can have its own configuration.
    |
    */

    'gateways' => [
        PaymentGateway::TRANSBANK->value => [
            'enabled' => env('TRANSBANK_ENABLED', true),
            'environment' => env('TRANSBANK_ENVIRONMENT', 'integration'),
            'commerce_code' => env('TRANSBANK_COMMERCE_CODE', '597055555532'),
            'api_key' => env('TRANSBANK_API_KEY', '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C'),
            'return_url' => env('TRANSBANK_RETURN_URL', env('APP_URL').'/payments/transbank/return'),
            'timeout' => 60, // segundos
        ],

        PaymentGateway::MERCADOPAGO->value => [
            'enabled' => env('MERCADOPAGO_ENABLED', true),
            'public_key' => env('MERCADOPAGO_PUBLIC_KEY', ''),
            'access_token' => env('MERCADOPAGO_ACCESS_TOKEN', ''),
            'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET', ''),
            'success_url' => env('MERCADOPAGO_SUCCESS_URL', env('APP_URL').'/payments/mercadopago/return'),
            'failure_url' => env('MERCADOPAGO_FAILURE_URL', env('APP_URL').'/payments/mercadopago/return'),
            'pending_url' => env('MERCADOPAGO_PENDING_URL', env('APP_URL').'/payments/mercadopago/return'),
        ],

        PaymentGateway::MANUAL->value => [
            'enabled' => env('MANUAL_PAYMENT_ENABLED', true),
            'name' => 'Pago Manual',
            'instructions' => 'Realizar transferencia a la cuenta bancaria y enviar comprobante.',
            'bank_accounts' => [
                [
                    'bank' => 'Banco de Chile',
                    'account_type' => 'Cuenta Corriente',
                    'account_number' => '0123456789',
                    'account_holder' => 'iLeben SpA',
                    'rut' => '76.XXX.XXX-X',
                ],
            ],
            'requires_proof' => true, // Requiere comprobante de pago
            'auto_expire_hours' => 48, // Expirar después de 48 horas sin confirmación
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Settings
    |--------------------------------------------------------------------------
    |
    | General payment-related configuration
    |
    */

    'currency' => env('PAYMENT_CURRENCY', 'CLP'),
    'timezone' => env('PAYMENT_TIMEZONE', 'America/Santiago'),

    // Tiempo máximo de espera para confirmación después de redirección (minutos)
    'confirmation_timeout' => 10,

    // Reintentos automáticos para pagos fallidos
    'auto_retry' => [
        'enabled' => false,
        'max_attempts' => 3,
        'delay_minutes' => 30,
    ],

    // Notificaciones
    'notifications' => [
        'email' => [
            'enabled' => true,
            'on_success' => true,
            'on_failure' => true,
        ],
        'admin_email' => env('PAYMENT_ADMIN_EMAIL', 'admin@example.com'),
    ],
];
