<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactSubmissionRequest;
use App\Jobs\CreateSalesforceCaseJob;
use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use App\Services\FinMail\FinMailNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContactSubmissionController extends Controller
{
    public function store(StoreContactSubmissionRequest $request): JsonResponse
    {
        $channel = $request->resolvedChannel();
        $fields = $request->validated('fields', []);
        $fields = $this->enrichMarketingFields($request, $fields);

        $name = $this->fieldValue($fields, ['name', 'nombre']);
        $email = $this->fieldValue($fields, ['email', 'correo']);
        $phone = $this->fieldValue($fields, ['phone', 'telefono', 'fono', 'celular', 'whatsapp']);
        $rut = $this->fieldValue($fields, ['rut']);

        $recipientEmail = $channel !== null
            ? $channel->effectiveNotificationEmail()
            : (SiteSetting::current()->contact_notification_email ?: SiteSetting::current()->contact_email);

        $submission = ContactSubmission::query()->create([
            'contact_channel_id' => $channel?->id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'rut' => $rut,
            'fields' => $fields,
            'recipient_email' => $recipientEmail,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 65535, ''),
            'submitted_at' => now(),
        ]);

        if (filled($recipientEmail)) {
            app(FinMailNotificationService::class)->sendContactSubmissionReceivedToAdmin($submission);
        }

        $leadEnabled = (bool) config('services.salesforce.lead_enabled', config('services.salesforce.case_enabled', false));

        if ($leadEnabled) {
            Log::info('ContactSubmissionController: Iniciando sincronización Salesforce Lead', [
                'contact_submission_id' => $submission->id,
                'email' => $submission->email,
            ]);

            CreateSalesforceCaseJob::dispatchSync($submission);

            Log::info('ContactSubmissionController: Finalizó sincronización Salesforce Lead', [
                'contact_submission_id' => $submission->id,
            ]);
        } else {
            Log::warning('ContactSubmissionController: Salesforce Lead deshabilitado, no se enviará a Salesforce', [
                'contact_submission_id' => $submission->id,
            ]);
        }

        return response()->json([
            'message' => 'Tu mensaje fue enviado correctamente.',
            'id' => $submission->id,
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function enrichMarketingFields(StoreContactSubmissionRequest $request, array $fields): array
    {
        $utmSite = trim((string) ($fields['utm_site'] ?? ''));

        if ($utmSite !== '') {
            return $fields;
        }

        $requestSourceSite = $this->resolveRequestSourceSite($request);

        if ($requestSourceSite === null) {
            return $fields;
        }

        $fields['utm_site'] = $requestSourceSite;

        return $fields;
    }

    private function resolveRequestSourceSite(StoreContactSubmissionRequest $request): ?string
    {
        $candidates = [
            (string) $request->headers->get('Origin', ''),
            (string) $request->headers->get('Referer', ''),
            (string) $request->headers->get('X-Source-Site', ''),
        ];

        foreach ($candidates as $candidate) {
            $normalized = trim($candidate);

            if ($normalized === '') {
                continue;
            }

            $host = parse_url($normalized, PHP_URL_HOST);

            if (is_string($host) && trim($host) !== '') {
                return trim(strtolower($host));
            }

            return $normalized;
        }

        $host = trim((string) $request->getHost());

        return $host !== '' ? strtolower($host) : null;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<int, string>  $aliases
     */
    private function fieldValue(array $fields, array $aliases): ?string
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
}
