<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Contracts\PaymentGatewayInterface driver(?string $gateway = null)
 * @method static \App\Contracts\PaymentGatewayInterface forPayment(\App\Models\Payment $payment)
 * @method static array available()
 * @method static bool isAvailable(string $gateway)
 * @method static array|null config(string $gateway)
 * @method static array createTransaction(array $data)
 * @method static array confirmTransaction(string $token)
 * @method static array getTransactionStatus(string $transactionId)
 * @method static array refundTransaction(string $transactionId, ?float $amount = null)
 * @method static bool processWebhook(array $payload)
 * @method static bool isEnabled()
 * @method static string getName()
 * @method static bool validateConfiguration()
 *
 * @see \App\Services\Payment\PaymentGatewayManager
 */
class PaymentGateway extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'payment.gateway';
    }
}
