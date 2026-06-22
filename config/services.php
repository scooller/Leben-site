<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'transbank' => [
        'environment' => env('TRANSBANK_ENVIRONMENT', 'integration'),
        'commerce_code' => env('TRANSBANK_COMMERCE_CODE'),
        'api_key' => env('TRANSBANK_API_KEY'),
    ],

    'mercadopago' => [
        'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
    ],

    'turnstile' => [
        'secret_key' => env('CLOUDFLARE_TURNSTILE_SECRET_KEY'),
    ],

    'salesforce' => [
        'auth_method' => env('SF_AUTH_METHOD', 'UserPassword'),
        'consumer_key' => env('SF_CONSUMER_KEY'),
        'consumer_secret' => env('SF_CONSUMER_SECRET'),
        'callback_uri' => env('SF_CALLBACK_URI'),
        'login_url' => env('SF_LOGIN_URL', 'https://login.salesforce.com'),
        'oauth_scope' => env('SF_OAUTH_SCOPE', 'api refresh_token offline_access'),
        'oauth_prompt' => env('SF_OAUTH_PROMPT', 'consent'),
        'username' => env('SF_USERNAME'),
        'password' => env('SF_PASSWORD'),
        'api_version' => env('SF_API_VERSION', '57.0'),
        'instance_url' => env('SF_INSTANCE_URL'),
        'public_site_url' => env('SF_PUBLIC_SITE_URL'),
        'org_id' => env('SF_ORG_ID'),
        'http_verify' => env('SF_HTTP_VERIFY', false),
        'locale' => env('SF_LOCALE', 'en_US'),
        'case_enabled' => env('SF_CASE_ENABLED', false),
        'case_record_type_id' => env('SF_CASE_RECORD_TYPE_ID'),
        'case_owner_id' => env('SF_CASE_OWNER_ID'),
        'case_source_id' => env('SF_CASE_SOURCE_ID'),
        'case_status' => env('SF_CASE_STATUS', 'Nuevo'),
        'case_priority' => env('SF_CASE_PRIORITY', 'Media'),
        'case_origin' => env('SF_CASE_ORIGIN', 'Web'),
        'lead_enabled' => env('SF_LEAD_ENABLED', false),
        'lead_owner_id' => env('SF_LEAD_OWNER_ID'),
        'lead_status' => env('SF_LEAD_STATUS', 'En Contacto'),
        'whatsapp_owner_name' => env('SF_WHATSAPP_OWNER_NAME', 'ANDREA'),
    ],

    'production_sync' => [
        'base_url' => env('PRODUCTION_SYNC_BASE_URL'),
        'token' => env('PRODUCTION_SYNC_TOKEN'),
        'authorized_url' => env('PRODUCTION_SYNC_AUTHORIZED_URL', env('APP_URL')),
        'timeout' => env('PRODUCTION_SYNC_TIMEOUT', 120),
        'http_verify' => env('PRODUCTION_SYNC_HTTP_VERIFY', false),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
