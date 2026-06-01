<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

final class FlowLogMatrix
{
    /**
     * @var array<string, string>
     */
    private const MATRIX = [
        // Payments - Transbank
        'payments.transbank.bridge_request' => 'debug',
        'payments.transbank.bridge_missing_params' => 'error',
        'payments.transbank.bridge_invalid_redirect_url' => 'warning',
        'payments.transbank.return_without_token' => 'warning',
        'payments.transbank.processing_return' => 'debug',
        'payments.transbank.payment_not_found' => 'warning',
        'payments.transbank.payment_completed' => 'info',
        'payments.transbank.payment_rejected' => 'warning',
        'payments.transbank.return_error' => 'error',
        'payments.transbank.resolve_project_commerce_code' => 'debug',
        'payments.transbank.project_code_fallback' => 'warning',
        'payments.transbank.invalid_child_code_integration' => 'warning',
        'payments.transbank.create_transaction_request' => 'debug',
        'payments.transbank.create_transaction_success' => 'info',
        'payments.transbank.create_transaction_invalid_response' => 'error',
        'payments.transbank.create_transaction_error' => 'error',
        'payments.transbank.confirm_transaction_request' => 'debug',
        'payments.transbank.confirm_transaction_success' => 'info',
        'payments.transbank.confirm_transaction_error' => 'error',
        'payments.transbank.webhook_not_implemented' => 'debug',
        'payments.transbank.status_request' => 'debug',
        'payments.transbank.status_error' => 'error',
        'payments.transbank.refund_request' => 'debug',
        'payments.transbank.refund_success' => 'info',
        'payments.transbank.refund_error' => 'error',

        // Payments - MercadoPago
        'payments.mercadopago.invalid_signature' => 'warning',
        'payments.mercadopago.webhook_received' => 'debug',
        'payments.mercadopago.webhook_missing_payment_id' => 'warning',
        'payments.mercadopago.payment_not_found' => 'warning',
        'payments.mercadopago.payment_updated' => 'info',
        'payments.mercadopago.webhook_unhandled_type' => 'debug',
        'payments.mercadopago.webhook_error' => 'error',
        'payments.mercadopago.return_received' => 'debug',
        'payments.mercadopago.return_error' => 'error',
        'payments.mercadopago.create_preference_request' => 'debug',
        'payments.mercadopago.create_preference_success' => 'info',
        'payments.mercadopago.create_preference_error' => 'error',
        'payments.mercadopago.get_payment_success' => 'debug',
        'payments.mercadopago.get_payment_error' => 'error',
        'payments.mercadopago.refund_request' => 'debug',
        'payments.mercadopago.refund_success' => 'info',
        'payments.mercadopago.refund_error' => 'error',
        'payments.mercadopago.service_webhook_received' => 'debug',
        'payments.mercadopago.service_webhook_processing' => 'debug',
        'payments.mercadopago.service_webhook_payment_fetched' => 'debug',
        'payments.mercadopago.service_webhook_payment_error' => 'error',
        'payments.mercadopago.signature_missing_data' => 'warning',
        'payments.mercadopago.signature_invalid_format' => 'warning',
        'payments.mercadopago.signature_timestamp_old' => 'warning',
        'payments.mercadopago.signature_invalid' => 'warning',
        'payments.mercadopago.signature_exception' => 'error',

        // Salesforce job
        'salesforce.job.start' => 'debug',
        'salesforce.job.lead_disabled' => 'info',
        'salesforce.job.submission_missing' => 'warning',
        'salesforce.job.oauth_reconnect_attempt' => 'info',
        'salesforce.job.oauth_reconnect_failed' => 'warning',
        'salesforce.job.oauth_reconnect_success' => 'info',
        'salesforce.job.lead_created' => 'info',
        'salesforce.job.missing_resource' => 'warning',
        'salesforce.job.retry_after_reconnect' => 'info',
        'salesforce.job.token_missing_reconnect_failed' => 'critical',
        'salesforce.job.invalid_grant' => 'critical',
        'salesforce.job.lead_error' => 'error',
        'salesforce.job.alert_email_failed' => 'warning',

        // Production sync
        'production_sync.config_missing' => 'warning',
        'production_sync.network_error' => 'error',
        'production_sync.http_unsuccessful' => 'warning',
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public static function write(string $event, string $message, array $context = []): void
    {
        Log::log(self::levelFor($event), $message, array_merge(['log_event' => $event], $context));
    }

    public static function levelFor(string $event): string
    {
        return self::MATRIX[$event] ?? 'info';
    }
}
