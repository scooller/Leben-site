<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\FinMail\FinMailNotificationService;

class PaymentObserver
{
    public function updated(Payment $payment): void
    {
        if (! config('payments.notifications.fin_mail.enabled', true)) {
            return;
        }

        if (! $payment->wasChanged('status')) {
            return;
        }

        $currentStatus = $payment->status instanceof PaymentStatus
            ? $payment->status
            : PaymentStatus::fromValue((string) $payment->status);

        if (! $currentStatus || ! in_array($currentStatus, $this->statusesThatTriggerNotification(), true)) {
            return;
        }

        app(FinMailNotificationService::class)
            ->sendPaymentStatusChanged($payment, $payment->getRawOriginal('status'));
    }

    /**
     * @return array<int, PaymentStatus>
     */
    private function statusesThatTriggerNotification(): array
    {
        $configuredStatuses = config('payments.notifications.fin_mail.on_statuses', []);

        if (! is_array($configuredStatuses)) {
            return $this->defaultStatuses();
        }

        $statuses = [];

        foreach ($configuredStatuses as $configuredStatus) {
            $status = PaymentStatus::fromValue((string) $configuredStatus);

            if ($status) {
                $statuses[] = $status;
            }
        }

        return $statuses !== [] ? $statuses : $this->defaultStatuses();
    }

    /**
     * @return array<int, PaymentStatus>
     */
    private function defaultStatuses(): array
    {
        return [
            PaymentStatus::AUTHORIZED,
            PaymentStatus::COMPLETED,
            PaymentStatus::FAILED,
            PaymentStatus::CANCELLED,
            PaymentStatus::EXPIRED,
            PaymentStatus::REFUNDED,
            PaymentStatus::PARTIALLY_REFUNDED,
        ];
    }
}
