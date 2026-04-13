<?php

namespace App\Services\FinMail;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PlantReservation;
use App\Models\User;
use FinityLabs\FinMail\Enums\EmailStatus;
use FinityLabs\FinMail\Helpers\TokenValue;
use FinityLabs\FinMail\Mail\TemplateMail;
use FinityLabs\FinMail\Models\EmailTemplate;
use FinityLabs\FinMail\Models\SentEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class FinMailNotificationService
{
    public function sendUnitReservationCreated(PlantReservation $reservation): void
    {
        $reservation->loadMissing(['user', 'plant.proyecto']);

        $recipient = $reservation->user?->email;
        if (! is_string($recipient) || $recipient === '') {
            return;
        }

        $this->sendTemplate(
            templateKey: 'unit-reserved',
            recipient: $recipient,
            models: [
                'user' => $reservation->user,
                'reservation' => $reservation,
                'plant' => $reservation->plant,
                'project' => $reservation->plant?->proyecto,
                'reservation_amount' => new TokenValue($this->resolveReservationAmount($reservation) ?? '-'),
                'reservation_currency' => new TokenValue($this->resolveReservationCurrency()),
            ],
            contextModel: $reservation,
            logContext: [
                'reservation_id' => $reservation->id,
                'user_id' => $reservation->user_id,
            ],
            errorLogMessage: 'FinMail: no se pudo enviar correo de reserva',
        );
    }

    public function sendPaymentStatusChanged(Payment $payment, string|PaymentStatus|null $previousStatus): void
    {
        $payment->loadMissing(['user', 'project', 'plant.proyecto']);

        $recipient = $payment->user?->email;
        if (! is_string($recipient) || $recipient === '') {
            return;
        }

        $currentStatus = $this->resolveStatusLabel($payment->status);
        $previousStatusLabel = $this->resolveStatusLabel($previousStatus);

        $this->sendTemplate(
            templateKey: 'payment-status-updated',
            recipient: $recipient,
            models: [
                'user' => $payment->user,
                'payment' => $payment,
                'plant' => $payment->plant,
                'project' => $payment->project ?? $payment->plant?->proyecto,
                'previous_status' => new TokenValue($previousStatusLabel),
                'current_status' => new TokenValue($currentStatus),
            ],
            contextModel: $payment,
            logContext: [
                'payment_id' => $payment->id,
                'user_id' => $payment->user_id,
                'previous_status' => $previousStatusLabel,
                'current_status' => $currentStatus,
            ],
            errorLogMessage: 'FinMail: no se pudo enviar correo de estado de pago',
        );
    }

    public function sendManualReservationCreated(Payment $payment, PlantReservation $reservation): void
    {
        $payment->loadMissing(['user', 'project', 'plant.proyecto']);
        $reservation->loadMissing(['plant.proyecto']);

        $recipient = $payment->user?->email;

        if (! is_string($recipient) || $recipient === '') {
            return;
        }

        $this->sendTemplate(
            templateKey: 'manual-reservation-created',
            recipient: $recipient,
            models: [
                'user' => $payment->user,
                'payment' => $payment,
                'plant' => $payment->plant,
                'project' => $payment->project ?? $payment->plant?->proyecto,
                'reservation' => $reservation,
            ],
            contextModel: $payment,
            logContext: [
                'payment_id' => $payment->id,
                'reservation_id' => $reservation->id,
                'user_id' => $payment->user_id,
            ],
            errorLogMessage: 'FinMail: no se pudo enviar correo de reserva manual',
        );
    }

    public function sendManualPaymentProofSubmittedToAdmins(Payment $payment): void
    {
        $payment->loadMissing(['user', 'project', 'plant.proyecto']);

        $adminRecipients = User::query()
            ->where('user_type', 'admin')
            ->whereNotNull('email')
            ->pluck('email')
            ->filter(static fn (mixed $email): bool => is_string($email) && $email !== '')
            ->unique()
            ->values();

        if ($adminRecipients->isEmpty()) {
            return;
        }

        foreach ($adminRecipients as $recipient) {
            $this->sendTemplate(
                templateKey: 'manual-payment-proof-submitted-admin',
                recipient: $recipient,
                models: [
                    'user' => $payment->user,
                    'payment' => $payment,
                    'plant' => $payment->plant,
                    'project' => $payment->project ?? $payment->plant?->proyecto,
                ],
                contextModel: $payment,
                logContext: [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'recipient' => $recipient,
                ],
                errorLogMessage: 'FinMail: no se pudo enviar correo a admin por comprobante manual',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $models
     * @param  array<string, mixed>  $logContext
     */
    private function sendTemplate(
        string $templateKey,
        string $recipient,
        array $models,
        ?Model $contextModel,
        array $logContext,
        string $errorLogMessage,
    ): void {
        $sentEmailLog = null;

        try {
            $template = EmailTemplate::findByKey($templateKey, app()->getLocale());

            if (! $template) {
                Log::warning('FinMail: plantilla no encontrada o inactiva', [
                    'template_key' => $templateKey,
                    ...$logContext,
                ]);

                return;
            }

            $sentEmailLog = $this->createSentEmailLog($template, $recipient, $contextModel);

            $mail = TemplateMail::make($templateKey, app()->getLocale())
                ->models($models)
                ->withLogging($sentEmailLog);

            $pendingMail = Mail::to($recipient);

            if (method_exists($pendingMail, 'sendNow')) {
                $pendingMail->sendNow($mail);
            } else {
                $pendingMail->send($mail);
            }
        } catch (\Throwable $e) {
            $sentEmailLog?->markAsFailed($e->getMessage());

            Log::warning($errorLogMessage, [
                ...$logContext,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function createSentEmailLog(EmailTemplate $template, string $recipient, ?Model $contextModel): ?SentEmail
    {
        $sentTable = config('fin-mail.table_names.sent', 'sent_emails');

        if (! Schema::hasTable($sentTable)) {
            return null;
        }

        return SentEmail::create([
            'email_template_id' => $template->id,
            'sender' => (string) config('mail.from.address', 'noreply@example.com'),
            'to' => [$recipient],
            'cc' => [],
            'bcc' => [],
            'subject' => (string) $template->subject,
            'rendered_body' => null,
            'attachments' => [],
            'status' => EmailStatus::Queued,
            'sent_by' => auth()->id(),
            'sendable_type' => $contextModel?->getMorphClass(),
            'sendable_id' => $contextModel?->getKey(),
        ]);
    }

    private function resolveStatusLabel(string|PaymentStatus|null $status): string
    {
        if ($status instanceof PaymentStatus) {
            return $status->label();
        }

        if (is_string($status)) {
            return PaymentStatus::fromValue($status)?->label() ?? $status;
        }

        return '-';
    }

    private function resolveReservationAmount(PlantReservation $reservation): ?string
    {
        $projectAmount = $reservation->plant?->proyecto?->valor_reserva_exigido_defecto_peso;
        if (is_numeric($projectAmount)) {
            return number_format((float) $projectAmount, 2, '.', '');
        }

        $plantBaseAmount = $reservation->plant?->precio_base;
        if (is_numeric($plantBaseAmount)) {
            return number_format((float) $plantBaseAmount, 2, '.', '');
        }

        return null;
    }

    private function resolveReservationCurrency(): string
    {
        return (string) config('payments.currency', 'CLP');
    }
}
