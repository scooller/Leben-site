<?php

namespace App\Services\FinMail;

use App\Enums\PaymentStatus;
use App\Models\ContactSubmission;
use App\Models\Payment;
use App\Models\PlantReservation;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Models\User;
use FinityLabs\FinMail\Enums\EmailStatus;
use FinityLabs\FinMail\Helpers\TokenValue;
use FinityLabs\FinMail\Mail\TemplateMail;
use FinityLabs\FinMail\Models\EmailTemplate;
use FinityLabs\FinMail\Models\SentEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

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

    public function sendManualPaymentProofSubmittedToAdmins(Payment $payment, ?string $paymentReviewUrl = null): void
    {
        $payment->loadMissing([
            'user',
            'project.asesores',
            'plant.asesor',
            'plant.proyecto.asesores',
        ]);

        $adminRecipients = $this->resolveAdminRecipients();

        if ($adminRecipients->isEmpty()) {
            return;
        }

        $advisorRecipients = $this->resolveManualPaymentAdvisorRecipients($payment);

        foreach ($adminRecipients as $recipient) {
            $this->sendTemplate(
                templateKey: 'manual-payment-proof-submitted-admin',
                recipient: $recipient,
                models: [
                    'user' => $payment->user,
                    'payment' => $payment,
                    'plant' => $payment->plant,
                    'project' => $payment->project ?? $payment->plant?->proyecto,
                    'payment_review_url' => new TokenValue($paymentReviewUrl ?? '-'),
                ],
                contextModel: $payment,
                logContext: [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'recipient' => $recipient,
                ],
                additionalCcRecipients: $advisorRecipients,
                errorLogMessage: 'FinMail: no se pudo enviar correo a admin por comprobante manual',
            );
        }
    }

    public function sendNonManualPaymentProofSubmittedToAdmins(Payment $payment, ?string $paymentReviewUrl = null): void
    {
        $payment->loadMissing(['user', 'project', 'plant.proyecto']);

        $adminRecipients = $this->resolveAdminRecipients();

        if ($adminRecipients->isEmpty()) {
            return;
        }

        $proofContactRecipients = $this->resolveNonManualProofContactRecipients();

        foreach ($adminRecipients as $recipient) {
            $this->sendTemplate(
                templateKey: 'payment-proof-submitted-admin',
                recipient: $recipient,
                models: [
                    'user' => $payment->user,
                    'payment' => $payment,
                    'plant' => $payment->plant,
                    'project' => $payment->project ?? $payment->plant?->proyecto,
                    'payment_review_url' => new TokenValue($paymentReviewUrl ?? '-'),
                ],
                contextModel: $payment,
                logContext: [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'recipient' => $recipient,
                ],
                additionalCcRecipients: $proofContactRecipients,
                errorLogMessage: 'FinMail: no se pudo enviar correo a admin por comprobante no manual',
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveManualPaymentAdvisorRecipients(Payment $payment): array
    {
        $plantAdvisorEmail = trim((string) ($payment->plant?->asesor?->email ?? ''));

        if ($plantAdvisorEmail !== '' && \filter_var($plantAdvisorEmail, \FILTER_VALIDATE_EMAIL) !== false) {
            return [$plantAdvisorEmail];
        }

        $project = $payment->project ?? $payment->plant?->proyecto;

        if (! $project instanceof Proyecto) {
            return [];
        }

        return $project->asesores
            ->pluck('email')
            ->filter(static fn (mixed $email): bool => is_string($email) && \filter_var($email, \FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function resolveAdminRecipients(): \Illuminate\Support\Collection
    {
        return User::query()
            ->get()
            ->filter(static fn (User $user): bool => $user->isAdmin())
            ->map(static fn (User $user): ?string => $user->email)
            ->filter(static fn (mixed $email): bool => is_string($email) && $email !== '')
            ->unique()
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function resolveNonManualProofContactRecipients(): array
    {
        $configuredEmail = trim((string) (SiteSetting::current()->gateway_proof_contact_email ?? ''));

        if ($configuredEmail === '' || \filter_var($configuredEmail, \FILTER_VALIDATE_EMAIL) === false) {
            return [];
        }

        return [$configuredEmail];
    }

    public function sendContactSubmissionReceivedToAdmin(ContactSubmission $submission): void
    {
        $recipient = $submission->recipient_email;

        if (! is_string($recipient) || $recipient === '') {
            return;
        }

        $fields = is_array($submission->fields) ? $submission->fields : [];
        [$firstName, $lastName] = $this->resolveContactNameParts($submission, $fields);
        $channelName = trim((string) ($submission->channel?->name ?? $submission->channel?->slug ?? ''));

        $this->sendTemplate(
            templateKey: 'contact-submission-received-admin',
            recipient: $recipient,
            models: [
                'nombre' => new TokenValue($firstName ?: '-'),
                'apellido' => new TokenValue($lastName ?: '-'),
                'rut' => new TokenValue($submission->rut ?: $this->extractFieldValue($fields, ['rut']) ?: '-'),
                'telefono' => new TokenValue($submission->phone ?: $this->extractFieldValue($fields, ['telefono', 'phone', 'celular', 'whatsapp']) ?: '-'),
                'email' => new TokenValue($submission->email ?: $this->extractFieldValue($fields, ['email', 'correo']) ?: '-'),
                'channel' => new TokenValue($channelName !== '' ? $channelName : '-'),
                'comuna' => new TokenValue($this->extractFieldValue($fields, ['comuna', 'commune', 'district', 'project_commune']) ?: '-'),
                'proyecto' => new TokenValue($this->extractFieldValue($fields, ['proyecto', 'project', 'project_name', 'nombre_proyecto']) ?: '-'),
                'medio' => new TokenValue($this->extractFieldValue($fields, ['medio', 'origen', 'lead_source', 'utm_source']) ?: 'Black'),
                'rango' => new TokenValue($this->extractFieldValue($fields, ['rango', 'renta', 'renta_liquida', 'income_range']) ?: '-'),
                'codeudor' => new TokenValue($this->extractFieldValue($fields, ['codeudor', 'coudedor', 'co_deudor']) ?: '-'),
                'buscas' => new TokenValue($this->extractFieldValue($fields, ['buscas', 'objetivo', 'buying_for']) ?: '-'),
                'elaboral' => new TokenValue($this->extractFieldValue($fields, ['elaboral', 'estado_laboral', 'laboral']) ?: '-'),
                'mensaje' => new TokenValue($this->extractFieldValue($fields, ['mensaje', 'message']) ?: '-'),
                'site_name' => new TokenValue((string) (SiteSetting::current()->site_name ?: config('app.name', 'iLeben'))),
                'site_url' => new TokenValue((string) config('app.url', 'https://sale.ileben.cl')),
            ],
            contextModel: $submission,
            logContext: [
                'contact_submission_id' => $submission->id,
                'recipient' => $recipient,
            ],
            errorLogMessage: 'FinMail: no se pudo enviar correo de contacto',
        );
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{0:string,1:string}
     */
    private function resolveContactNameParts(ContactSubmission $submission, array $fields): array
    {
        $firstName = $this->extractFieldValue($fields, ['nombre', 'name']) ?? '';
        $lastName = $this->extractFieldValue($fields, ['apellido', 'apellidos', 'last_name', 'lastname']) ?? '';
        $fullName = trim((string) ($submission->name ?? ''));

        if ($firstName === '' && $fullName !== '') {
            $parts = preg_split('/\s+/', $fullName) ?: [];
            $firstName = trim((string) ($parts[0] ?? ''));
            $lastName = $lastName !== '' ? $lastName : trim(implode(' ', array_slice($parts, 1)));
        }

        return [$firstName, $lastName];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function extractFieldValue(array $fields, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            if (! array_key_exists($alias, $fields)) {
                continue;
            }

            $value = trim((string) $fields[$alias]);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
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
        array $additionalCcRecipients = [],
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

            $ccRecipients = array_values(array_filter(array_unique([
                ...$this->resolveTemplateCcRecipients($templateKey),
                ...$additionalCcRecipients,
            ]), static fn (mixed $email) => is_string($email) && $email !== '' && \filter_var($email, \FILTER_VALIDATE_EMAIL) !== false && \strcasecmp((string) $email, $recipient) !== 0));
            $sentEmailLog = $this->createSentEmailLog($template, $recipient, $ccRecipients, $contextModel);

            $mail = TemplateMail::make($templateKey, app()->getLocale())
                ->models($models)
                ->withLogging($sentEmailLog);

            $pendingMail = Mail::to($recipient);

            if ($ccRecipients !== []) {
                $pendingMail->cc($ccRecipients);
            }

            if (\method_exists($pendingMail, 'sendNow')) {
                $pendingMail->sendNow($mail);
            } else {
                $pendingMail->send($mail);
            }
        } catch (Throwable $e) {
            $sentEmailLog?->markAsFailed($e->getMessage());

            Log::warning($errorLogMessage, [
                ...$logContext,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function createSentEmailLog(EmailTemplate $template, string $recipient, array $ccRecipients, ?Model $contextModel): ?SentEmail
    {
        $sentTable = config('fin-mail.table_names.sent', 'sent_emails');

        if (! Schema::hasTable($sentTable)) {
            return null;
        }

        return SentEmail::create([
            'email_template_id' => $template->id,
            'sender' => (string) config('mail.from.address', 'noreply@example.com'),
            'to' => [$recipient],
            'cc' => $ccRecipients,
            'bcc' => [],
            'subject' => (string) $template->subject,
            'rendered_body' => null,
            'attachments' => [],
            'status' => EmailStatus::Queued,
            'sent_by' => Auth::user()?->getAuthIdentifier(),
            'sendable_type' => $contextModel?->getMorphClass(),
            'sendable_id' => $contextModel?->getKey(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function resolveTemplateCcRecipients(string $templateKey): array
    {
        $configuredRecipients = config("mail.template_cc.{$templateKey}", []);

        if (is_string($configuredRecipients)) {
            $configuredRecipients = explode(',', $configuredRecipients);
        }

        if (! is_array($configuredRecipients)) {
            return [];
        }

        return array_values(array_filter(array_unique(array_map(
            static fn (mixed $email): string => trim((string) $email),
            $configuredRecipients,
        )), static fn (string $email): bool => $email !== '' && \filter_var($email, \FILTER_VALIDATE_EMAIL) !== false));
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
