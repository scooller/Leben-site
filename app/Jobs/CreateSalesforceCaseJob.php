<?php

namespace App\Jobs;

use App\Exceptions\SalesforceTokenExpiredException;
use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Salesforce\SalesforceCaseMapper;
use App\Services\Salesforce\SalesforceService;
use App\Support\FlowLogMatrix;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Omniphx\Forrest\Exceptions\MissingResourceException;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Throwable;

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

        FlowLogMatrix::write('salesforce.job.start', 'CreateSalesforceCaseJob: Inicio de ejecución', [
            'contact_submission_id' => $this->submission->id,
            'lead_enabled' => $leadEnabled,
        ]);

        if (! $leadEnabled) {
            FlowLogMatrix::write('salesforce.job.lead_disabled', 'CreateSalesforceCaseJob: Lead deshabilitado, se omite envío', [
                'contact_submission_id' => $this->submission->id,
            ]);

            return;
        }

        $submission = $this->submission->fresh();

        if (! $submission) {
            FlowLogMatrix::write('salesforce.job.submission_missing', 'CreateSalesforceCaseJob: Submission no encontrada al refrescar');

            return;
        }

        $settings = SiteSetting::current();
        if ($syncTrigger === 'automatic' && ! $settings->shouldSyncInactiveProjectsToSalesforce()) {
            $project = $mapper->resolveProjectForSubmission($submission);

            if ($project !== null && ! (bool) $project->is_active) {
                FlowLogMatrix::write('salesforce.job.inactive_project_skipped', 'CreateSalesforceCaseJob: Proyecto inactivo y sincronización automática desactivada, se omite envío', [
                    'contact_submission_id' => $submission->id,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                ]);

                $submission->update([
                    'salesforce_case_error' => 'Omitido: Proyecto inactivo (sincronización automática desactivada).',
                    'salesforce_synced_at' => now(),
                    'salesforce_sync_trigger' => $syncTrigger,
                ]);

                return;
            }
        }

        // Si el OAuth está marcado como desconectado o no hay token en caché, intentar
        // auto-reconexión silenciosa con el refresh_token del backup en DB antes de rendirse.
        // Esto cubre: rotación de refresh_token, cache:clear, restart de Redis, o fallo transitorio.
        if ($this->isSalesforceOAuthMarkedDisconnected() || ! Forrest::hasToken()) {
            FlowLogMatrix::write('salesforce.job.oauth_reconnect_attempt', 'CreateSalesforceCaseJob: Token no disponible o OAuth desconectado. Intentando auto-reconexión silenciosa.', [
                'contact_submission_id' => $submission->id,
                'disconnected_flag' => $this->isSalesforceOAuthMarkedDisconnected(),
                'has_token' => Forrest::hasToken(),
            ]);

            $reconnected = $salesforceService->tryAutoReconnect();

            if (! $reconnected) {
                FlowLogMatrix::write('salesforce.job.oauth_reconnect_failed', 'CreateSalesforceCaseJob: OAuth de Salesforce desconectado y auto-reconexión fallida. Se omite envío hasta reconexión manual.', [
                    'contact_submission_id' => $submission->id,
                ]);

                $this->notifySalesforceOAuthDisconnection($submission->id, 'Auto-reconexión fallida desde CreateSalesforceCaseJob.');

                $submission->update([
                    'salesforce_case_error' => 'OAuth Salesforce desconectado. Auto-reconexión fallida. Reconectar en panel admin.',
                    'salesforce_synced_at' => now(),
                    'salesforce_sync_trigger' => $syncTrigger,
                ]);

                // // enviar email de alerta de desconexión OAuth a destinatario administrativo
                // // Obtener todos los usuarios administradores
                // $administrators = User::role('admin')->get();
                // // $administrators = User::where('is_admin', true)->get();
                // // Enviar correo a cada administrador
                // foreach ($administrators as $admin) {
                // 	Mail::send(
                // 		'emails.admin-message', // Vista del correo
                // 		[
                // 			'titulo' => 'Alerta: Salesforce OAuth desconectado',
                // 			'contenido' => 'Se detectó que el OAuth de Salesforce está desconectado y la auto-reconexión falló. Esto significa que no se podrán crear casos/leads en Salesforce hasta que se reconecte manualmente desde el panel admin.'
                // 		],
                // 		function ($message) use ($admin) {
                // 			$message->to($admin->email)
                // 				->subject('Alerta: Salesforce OAuth desconectado');
                // 		}
                // 	);
                // }

                return;
            }

            // Auto-reconexión exitosa — el token ya está en caché, continuar normalmente
            FlowLogMatrix::write('salesforce.job.oauth_reconnect_success', 'CreateSalesforceCaseJob: Auto-reconexión exitosa. Continuando con envío a Salesforce.', [
                'contact_submission_id' => $submission->id,
            ]);
        }

        try {
            // WebServer flow: el token se obtiene vía OAuth interactivo desde el panel admin.
            // No llamar authenticate() aquí porque en WebServer flow intenta redirigir al navegador.
            // Si no hay token, Forrest lanzará MissingResourceException con mensaje claro.

            // Flujo Case pausado temporalmente:
            // $payload = $mapper->map($submission);
            // $response = $salesforceService->createCase($payload);

            $payload = $mapper->mapLead($submission);

            if (filled($submission->salesforce_case_id)) {
                try {
                    $response = $salesforceService->updateLead((string) $submission->salesforce_case_id, $payload);
                    $leadId = (string) $submission->salesforce_case_id;

                    $submission->update([
                        'salesforce_case_error' => null,
                        'salesforce_synced_at' => now(),
                        'salesforce_sync_trigger' => $syncTrigger,
                    ]);

                    FlowLogMatrix::write('salesforce.job.lead_updated', 'CreateSalesforceCaseJob: Lead actualizado correctamente', [
                        'contact_submission_id' => $submission->id,
                        'salesforce_lead_id' => $leadId,
                        'salesforce_success' => $response['success'] ?? true,
                    ]);

                    return;
                } catch (SalesforceTokenExpiredException|MissingResourceException $exception) {
                    throw $exception;
                } catch (Throwable $updateException) {
                    $message = strtolower($updateException->getMessage());
                    $context = $this->extractExceptionContext($updateException);
                    $responseBodyStr = strtolower(json_encode($context['salesforce_error_response'] ?? ''));

                    $isNotFound = str_contains($message, 'not_found')
                        || str_contains($message, 'entity_is_deleted')
                        || str_contains($message, 'provided id does not exist')
                        || str_contains($message, '404')
                        || str_contains($responseBodyStr, 'not_found')
                        || str_contains($responseBodyStr, 'entity_is_deleted');

                    $isConvertedLead = str_contains($message, 'cannot_update_converted_lead')
                        || str_contains($message, 'cannot reference converted lead')
                        || str_contains($responseBodyStr, 'cannot_update_converted_lead')
                        || str_contains($responseBodyStr, 'cannot reference converted lead');

                    if ($isNotFound || $isConvertedLead) {
                        $reason = $isConvertedLead
                            ? 'Lead en Salesforce ya fue convertido a Cuenta/Contacto/Oportunidad, creando nuevo Lead'
                            : 'Lead previo no encontrado o eliminado en Salesforce, creando nuevo Lead';

                        FlowLogMatrix::write('salesforce.job.lead_update_fallback', "CreateSalesforceCaseJob: {$reason}", [
                            'contact_submission_id' => $submission->id,
                            'old_lead_id' => $submission->salesforce_case_id,
                            'fallback_reason' => $isConvertedLead ? 'converted_lead' : 'not_found',
                            'error' => $updateException->getMessage(),
                            'salesforce_error_response' => $context['salesforce_error_response'] ?? null,
                        ]);
                    } else {
                        throw $updateException;
                    }
                }
            }

            $response = $salesforceService->createLead($payload);
            $leadId = (string) ($response['id'] ?? $response['Id'] ?? '');

            $submission->update([
                'salesforce_case_id' => $leadId !== '' ? $leadId : null,
                'salesforce_case_error' => null,
                'salesforce_synced_at' => now(),
                'salesforce_sync_trigger' => $syncTrigger,
            ]);

            FlowLogMatrix::write('salesforce.job.lead_created', 'CreateSalesforceCaseJob: Lead creado correctamente', [
                'contact_submission_id' => $submission->id,
                'salesforce_lead_id' => $leadId !== '' ? $leadId : null,
                'salesforce_success' => $response['success'] ?? null,
                'salesforce_errors' => $response['errors'] ?? null,
                'salesforce_response_keys' => array_keys($response),
                'salesforce_error_count' => is_array($response['errors'] ?? null)
                    ? count($response['errors'])
                    : null,
            ]);
        } catch (MissingResourceException $exception) {
            // Token no disponible — intentar auto-reconexión como último recurso
            FlowLogMatrix::write('salesforce.job.missing_resource', 'CreateSalesforceCaseJob: MissingResourceException durante la operación. Intentando auto-reconexión.', [
                'contact_submission_id' => $submission->id,
            ]);

            if ($salesforceService->tryAutoReconnect()) {
                FlowLogMatrix::write('salesforce.job.retry_after_reconnect', 'CreateSalesforceCaseJob: Auto-reconexión exitosa tras MissingResourceException. Reintentando operación.', [
                    'contact_submission_id' => $submission->id,
                ]);

                // Relanzar el job para que se reintente con el token renovado
                $this->release(5);

                return;
            }

            FlowLogMatrix::write('salesforce.job.token_missing_reconnect_failed', 'CreateSalesforceCaseJob: Token de Salesforce no disponible y auto-reconexión fallida. Reconecta en /admin/site-settings → "Conectar con Salesforce"', [
                'contact_submission_id' => $submission->id,
            ]);

            $salesforceService->markAsDisconnected('Token no disponible en cache (MissingResourceException) y auto-reconexión fallida.');
            $this->notifySalesforceOAuthDisconnection($submission->id, 'Token no disponible en cache (MissingResourceException) y auto-reconexión fallida.');

            $submission->update([
                'salesforce_case_error' => 'Token Salesforce expirado y auto-reconexión fallida. Reconectar en panel admin.',
                'salesforce_synced_at' => now(),
                'salesforce_sync_trigger' => $syncTrigger,
            ]);

            // // enviar email de alerta de desconexión OAuth a destinatario administrativo
            // // Obtener todos los usuarios administradores
            // $administrators = User::role('admin')->get();
            // // $administrators = User::where('is_admin', true)->get();
            // // Enviar correo a cada administrador
            // foreach ($administrators as $admin) {
            // 	Mail::send(
            // 		'emails.admin-message', // Vista del correo
            // 		[
            // 			'titulo' => 'Alerta: Salesforce OAuth desconectado Token no disponible',
            // 			'contenido' => 'Se detectó que el OAuth de Salesforce está desconectado y la auto-reconexión falló. Esto significa que no se podrán crear casos/leads en Salesforce hasta que se reconecte manualmente desde el panel admin.'
            // 		],
            // 		function ($message) use ($admin) {
            // 			$message->to($admin->email)
            // 				->subject('Alerta: Salesforce OAuth desconectado Token no disponible');
            // 		}
            // 	);
            // }

            // No relanzar — no tiene sentido reintentar sin token
        } catch (SalesforceTokenExpiredException $exception) {
            FlowLogMatrix::write('salesforce.job.invalid_grant', 'CreateSalesforceCaseJob: Salesforce respondió invalid_grant. Reconecta en /admin/site-settings → "Conectar con Salesforce"', [
                'contact_submission_id' => $submission->id,
                'error' => $exception->getMessage(),
            ]);

            $salesforceService->markAsDisconnected('invalid_grant: expired access/refresh token');
            $this->notifySalesforceOAuthDisconnection($submission->id, 'invalid_grant: expired access/refresh token');

            $submission->update([
                'salesforce_case_error' => 'Token Salesforce revocado/expirado (invalid_grant). Reconectar en panel admin.',
                'salesforce_synced_at' => now(),
                'salesforce_sync_trigger' => $syncTrigger,
            ]);
        } catch (Throwable $exception) {
            $context = $this->extractExceptionContext($exception);

            $detailedMessage = null;
            if (! empty($context['salesforce_error_response'])) {
                if (is_array($context['salesforce_error_response'])) {
                    $errors = [];
                    foreach ($context['salesforce_error_response'] as $err) {
                        if (is_array($err)) {
                            $msg = $err['message'] ?? '';
                            $code = $err['errorCode'] ?? '';
                            $fields = ! empty($err['fields']) ? ' (campos: '.implode(', ', (array) $err['fields']).')' : '';
                            $errors[] = trim("{$code}: {$msg}{$fields}", ': ');
                        } elseif (is_string($err)) {
                            $errors[] = $err;
                        }
                    }
                    if ($errors !== []) {
                        $detailedMessage = implode(' | ', $errors);
                    }
                } elseif (is_string($context['salesforce_error_response'])) {
                    $detailedMessage = $context['salesforce_error_response'];
                }
            }

            $errorMessage = $detailedMessage
                ? Str::limit($exception->getMessage().' -> '.$detailedMessage, 65535, '')
                : Str::limit($exception->getMessage(), 65535, '');

            $submission->update([
                'salesforce_case_error' => $errorMessage,
                'salesforce_synced_at' => now(),
                'salesforce_sync_trigger' => $syncTrigger,
            ]);

            $flowMessage = $detailedMessage
                ? "CreateSalesforceCaseJob: Error al procesar Lead en Salesforce - [{$detailedMessage}]"
                : 'CreateSalesforceCaseJob: Error al procesar Lead en Salesforce';

            FlowLogMatrix::write('salesforce.job.lead_error', $flowMessage, [
                'contact_submission_id' => $submission->id,
                'salesforce_case_id' => $submission->salesforce_case_id,
                'action' => filled($submission->salesforce_case_id) ? 'update' : 'create',
                'exception_message' => $exception->getMessage(),
                'detailed_salesforce_error' => $detailedMessage,
                ...$context,
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

        $rawBody = null;
        if (method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();

            if ($response) {
                $context['salesforce_http_status'] = $response->getStatusCode();
                $bodyStream = $response->getBody();
                if (method_exists($bodyStream, 'isSeekable') && $bodyStream->isSeekable()) {
                    $bodyStream->rewind();
                }
                $rawBody = (string) $bodyStream;
            }
        }

        // Si el response body está vacío, intentar extraer JSON embebido en el mensaje de la excepción (e.g. Guzzle)
        if (($rawBody === null || trim($rawBody) === '') && preg_match('/(\[\s*\{.*?\}\s*\]|\{\s*".*?"\s*:.*?\})/s', $exception->getMessage(), $matches)) {
            $rawBody = $matches[1];
        }

        if ($rawBody !== null && trim($rawBody) !== '') {
            $decodedBody = \json_decode($rawBody, true);
            $context['salesforce_error_response'] = is_array($decodedBody)
                ? $decodedBody
                : Str::limit($rawBody, 4000, '');
        }

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

    private function notifySalesforceOAuthDisconnection(int $contactSubmissionId, string $reason): void
    {
        $siteSettings = SiteSetting::current();
        $recipient = $this->resolveSalesforceAlertRecipient($siteSettings);

        if ($recipient === []) {
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
        } catch (Throwable $exception) {
            FlowLogMatrix::write('salesforce.job.alert_email_failed', 'CreateSalesforceCaseJob: No se pudo enviar alerta de desconexión OAuth de Salesforce', [
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveSalesforceAlertRecipient(SiteSetting $siteSettings): array
    {
        $emails = [];

        $candidates = [
            trim((string) $siteSettings->contact_notification_email),
            trim((string) $siteSettings->contact_email),
            trim((string) config('mail.from.address', '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false) {
                $emails[] = $candidate;
            }
        }

        $adminEmails = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->all();

        return array_values(array_unique([
            ...$emails,
            ...$adminEmails,
        ]));
    }
}
