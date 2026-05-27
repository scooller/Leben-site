<?php

namespace App\Jobs;

use App\Exceptions\SalesforceTokenExpiredException;
use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use App\Services\Salesforce\SalesforceCaseMapper;
use App\Services\Salesforce\SalesforceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Omniphx\Forrest\Exceptions\MissingResourceException;

class CreateSalesforceCaseJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public ContactSubmission $submission, public string $syncTrigger = 'automatic') {}

    /**
     * Execute the job.
     */
    public function handle(SalesforceService $salesforceService, SalesforceCaseMapper $mapper): void
    {
        $syncTrigger = $this->normalizeSyncTrigger($this->syncTrigger);
        $leadEnabled = (bool) config('services.salesforce.lead_enabled', config('services.salesforce.case_enabled', false));

        Log::debug('CreateSalesforceCaseJob: Inicio de ejecución', [
            'contact_submission_id' => $this->submission->id,
            'lead_enabled' => $leadEnabled,
        ]);

        if (! $leadEnabled) {
            Log::warning('CreateSalesforceCaseJob: Lead deshabilitado, se omite envío', [
                'contact_submission_id' => $this->submission->id,
            ]);

            return;
        }

        $submission = $this->submission->fresh();

        if (! $submission) {
            Log::warning('CreateSalesforceCaseJob: Submission no encontrada al refrescar');

            return;
        }

        if ($this->isSalesforceOAuthMarkedDisconnected()) {
            Log::warning('CreateSalesforceCaseJob: OAuth de Salesforce marcado como desconectado. Se omite envío hasta reconexión manual.', [
                'contact_submission_id' => $submission->id,
            ]);

            $submission->update([
                'salesforce_case_error' => 'OAuth Salesforce desconectado. Reconectar en panel admin antes de reintentar.',
                'salesforce_synced_at' => now(),
                'salesforce_sync_trigger' => $syncTrigger,
            ]);

            return;
        }

        try {
            // WebServer flow: el token se obtiene vía OAuth interactivo desde el panel admin.
            // No llamar authenticate() aquí porque en WebServer flow intenta redirigir al navegador.
            // Si no hay token, Forrest lanzará MissingResourceException con mensaje claro.

            // Flujo Case pausado temporalmente:
            // $payload = $mapper->map($submission);
            // $response = $salesforceService->createCase($payload);

            $payload = $mapper->mapLead($submission);
            $response = $salesforceService->createLead($payload);
            $leadId = (string) ($response['id'] ?? $response['Id'] ?? '');

            $submission->update([
                'salesforce_case_id' => $leadId !== '' ? $leadId : null,
                'salesforce_case_error' => null,
                'salesforce_synced_at' => now(),
                'salesforce_sync_trigger' => $syncTrigger,
            ]);

            Log::debug('CreateSalesforceCaseJob: Lead creado correctamente', [
                'contact_submission_id' => $submission->id,
                'salesforce_lead_id' => $leadId !== '' ? $leadId : null,
                'salesforce_success' => $response['success'] ?? null,
                'salesforce_errors' => $response['errors'] ?? null,
                'salesforce_response' => $response,
            ]);
        } catch (\Omniphx\Forrest\Exceptions\MissingResourceException $exception) {
            // Token no disponible en cache — requiere reconexión OAuth desde el panel admin
            Log::critical('CreateSalesforceCaseJob: Token de Salesforce no disponible. Reconecta en /admin/site-settings → "Conectar con Salesforce"', [
                'contact_submission_id' => $submission->id,
            ]);

            $this->markSalesforceOAuthAsDisconnected('Token no disponible en cache (MissingResourceException).');
            $this->notifySalesforceOAuthDisconnection($submission->id, 'Token no disponible en cache (MissingResourceException).');

            $submission->update([
                'salesforce_case_error' => 'Token Salesforce expirado. Reconectar en panel admin.',
                'salesforce_synced_at' => now(),
                'salesforce_sync_trigger' => $syncTrigger,
            ]);

            // No relanzar — no tiene sentido reintentar sin token
        } catch (SalesforceTokenExpiredException $exception) {
            Log::critical('CreateSalesforceCaseJob: Salesforce respondió invalid_grant. Reconecta en /admin/site-settings → "Conectar con Salesforce"', [
                'contact_submission_id' => $submission->id,
                'error' => $exception->getMessage(),
            ]);

            $this->markSalesforceOAuthAsDisconnected('invalid_grant: expired access/refresh token');
            $this->notifySalesforceOAuthDisconnection($submission->id, 'invalid_grant: expired access/refresh token');

            $submission->update([
                'salesforce_case_error' => 'Token Salesforce revocado/expirado (invalid_grant). Reconectar en panel admin.',
                'salesforce_synced_at' => now(),
                'salesforce_sync_trigger' => $syncTrigger,
            ]);
        } catch (\Throwable $exception) {
            $errorMessage = Str::limit($exception->getMessage(), 65535, '');

            $submission->update([
                'salesforce_case_error' => $errorMessage,
                'salesforce_synced_at' => now(),
                'salesforce_sync_trigger' => $syncTrigger,
            ]);

            Log::error('CreateSalesforceCaseJob: Error al crear Lead', [
                'contact_submission_id' => $submission->id,
                'error' => $exception->getMessage(),
                ...$this->extractExceptionContext($exception),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractExceptionContext(\Throwable $exception): array
    {
        $context = [
            'exception_class' => $exception::class,
        ];

        if (! method_exists($exception, 'getResponse')) {
            return $context;
        }

        $response = $exception->getResponse();

        if (! $response) {
            return $context;
        }

        $body = (string) $response->getBody();
        $decodedBody = \json_decode($body, true);

        $context['salesforce_http_status'] = $response->getStatusCode();
        $context['salesforce_error_response'] = is_array($decodedBody)
            ? $decodedBody
            : Str::limit($body, 4000, '');

        return $context;
    }

    private function normalizeSyncTrigger(string $syncTrigger): string
    {
        return $syncTrigger === 'manual' ? 'manual' : 'automatic';
    }

    private function isSalesforceOAuthMarkedDisconnected(): bool
    {
        $extraSettings = SiteSetting::current()->extra_settings;

        if (! is_array($extraSettings)) {
            return false;
        }

        return data_get($extraSettings, 'salesforce_oauth.connected') === false;
    }

    private function markSalesforceOAuthAsDisconnected(string $reason): void
    {
        $siteSettings = SiteSetting::current();
        $extraSettings = is_array($siteSettings->extra_settings) ? $siteSettings->extra_settings : [];

        data_set($extraSettings, 'salesforce_oauth.connected', false);
        data_set($extraSettings, 'salesforce_oauth.last_disconnected_at', now()->toIso8601String());
        data_set($extraSettings, 'salesforce_oauth.last_error', $reason);

        $siteSettings->update([
            'extra_settings' => $extraSettings,
        ]);
    }

    private function notifySalesforceOAuthDisconnection(int $contactSubmissionId, string $reason): void
    {
        $siteSettings = SiteSetting::current();
        $recipient = $this->resolveSalesforceAlertRecipient($siteSettings);

        if ($recipient === null) {
            return;
        }

        $alertKey = sprintf('salesforce:oauth:disconnect-alert:%s', md5(strtolower($reason)));
        $shouldSend = Cache::add($alertKey, now()->toIso8601String(), now()->addMinutes(30));

        if (! $shouldSend) {
            return;
        }

        $subject = 'Alerta Salesforce OAuth desconectado';
        $message = implode("\n", [
            'Se detectó desconexión OAuth con Salesforce.',
            'Submission ID: '.$contactSubmissionId,
            'Motivo: '.$reason,
            'Acción requerida: reconectar en /admin/site-settings (Conectar con Salesforce).',
        ]);

        try {
            Mail::raw($message, static function ($mail) use ($recipient, $subject): void {
                $mail->to($recipient)->subject($subject);
            });
        } catch (\Throwable $exception) {
            Log::warning('CreateSalesforceCaseJob: No se pudo enviar alerta de desconexión OAuth de Salesforce', [
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveSalesforceAlertRecipient(SiteSetting $siteSettings): ?string
    {
        $candidates = [
            trim((string) $siteSettings->contact_notification_email),
            trim((string) $siteSettings->contact_email),
            trim((string) config('mail.from.address', '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false) {
                return $candidate;
            }
        }

        return null;
    }
}
