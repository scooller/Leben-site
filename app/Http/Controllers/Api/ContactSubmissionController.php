<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactSubmissionRequest;
use App\Jobs\CreateSalesforceCaseJob;
use App\Models\ContactSubmission;
use App\Models\Proyecto;
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
        $fields = $this->enrichMarketingFields($request, $fields, $channel);
        $fields = $this->enrichProjectSalesforceId($fields);

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
            ]);

            CreateSalesforceCaseJob::dispatchSync($submission, 'automatic');

            Log::info('ContactSubmissionController: Finalizó sincronización Salesforce Lead', [
                'contact_submission_id' => $submission->id,
            ]);
        } else {
            Log::info('ContactSubmissionController: Salesforce Lead deshabilitado, no se enviará a Salesforce', [
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
    private function enrichMarketingFields(StoreContactSubmissionRequest $request, array $fields, ?\App\Models\ContactChannel $channel = null): array
    {
        $settings = SiteSetting::current();

        // Solo sobreescribir utm_campaign si proviene del front, el evento Sale está activo
        // y el canal actual está seleccionado en sale_utm_campaign_channels.
        // Peticiones provenientes de API externa o canales no seleccionados preservan su campaña original.
        if ($settings->evento_sale && $this->isFrontendRequest($request, $channel) && $settings->isChannelEligibleForSaleUtmCampaign($channel)) {
            $extraSettings = is_array($settings->extra_settings) ? $settings->extra_settings : [];
            $saleCampaign = trim((string) ($extraSettings['sale_utm_campaign'] ?? ''))
                ?: trim((string) ($extraSettings['sale_event_name'] ?? ''))
                ?: trim((string) ($extraSettings['utm_campaign_default'] ?? ''));

            if ($saleCampaign !== '') {
                $utmSource = $this->fieldValue($fields, ['utm_source', 'fuente', 'medio_de_llegada', 'medio_llegada', 'origen_del_prospecto', 'origen_prospecto']);
                $currentCampaign = $this->fieldValue($fields, ['utm_campaign', 'campana', 'nombre_de_la_campana']);
                $isDefaultOrMissing = $currentCampaign === null || trim($currentCampaign) === '' || in_array(strtolower(trim($currentCampaign)), ['auto-tagging', 'campaign'], true);

                if (($utmSource !== null && strtolower(trim($utmSource)) === 'brevo') || $isDefaultOrMissing) {
                    $fields['utm_campaign'] = $saleCampaign;
                }
            }
        }

        $utmSite = trim((string) ($fields['utm_site'] ?? ''));

        if ($utmSite !== '') {
            return $fields;
        }

        $requestSourceSite = $this->resolveRequestSourceSite($request, $channel);

        if ($requestSourceSite !== null) {
            $fields['utm_site'] = $requestSourceSite;
        }

        return $fields;
    }

    private function isFrontendRequest(StoreContactSubmissionRequest $request, ?\App\Models\ContactChannel $channel = null): bool
    {
        if ($request->headers->has('X-Contact-Channel')) {
            return false;
        }

        $origin = $request->headers->get('Origin');
        $referer = $request->headers->get('Referer');

        if (blank($origin) && blank($referer)) {
            return false;
        }

        $requestHosts = [];
        foreach ([$origin, $referer] as $headerValue) {
            if (filled($headerValue)) {
                $host = parse_url((string) $headerValue, PHP_URL_HOST);
                if (filled($host)) {
                    $requestHosts[] = strtolower((string) $host);
                }
            }
        }

        if (empty($requestHosts)) {
            return false;
        }

        $allowedHosts = $this->resolveAllowedFrontendHosts($channel);

        foreach ($requestHosts as $host) {
            if (in_array($host, $allowedHosts, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function resolveAllowedFrontendHosts(?\App\Models\ContactChannel $channel = null): array
    {
        $hosts = [];

        $settings = SiteSetting::current();
        if (filled($settings->site_url)) {
            $host = parse_url((string) $settings->site_url, PHP_URL_HOST);
            if (filled($host)) {
                $hosts[] = strtolower((string) $host);
            }
        }

        $frontendUrl = config('app.frontend_url');
        if (filled($frontendUrl)) {
            $host = parse_url((string) $frontendUrl, PHP_URL_HOST);
            if (filled($host)) {
                $hosts[] = strtolower((string) $host);
            }
        }

        $appUrl = config('app.url');
        if (filled($appUrl)) {
            $host = parse_url((string) $appUrl, PHP_URL_HOST);
            if (filled($host)) {
                $hosts[] = strtolower((string) $host);
            }
        }

        if ($channel !== null && is_array($channel->domain_patterns)) {
            foreach ($channel->domain_patterns as $pattern) {
                $clean = strtolower(trim((string) $pattern));
                if ($clean !== '') {
                    $hosts[] = $clean;
                }
            }
        }

        $hosts[] = 'localhost';
        $hosts[] = '127.0.0.1';

        return array_values(array_unique($hosts));
    }

    private function resolveRequestSourceSite(StoreContactSubmissionRequest $request, ?\App\Models\ContactChannel $channel = null): ?string
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

        if ($channel !== null) {
            foreach ((array) ($channel->domain_patterns ?? []) as $pattern) {
                $normalized = strtolower(trim((string) $pattern));
                $normalized = str_replace(['*.', '*'], '', $normalized);
                $normalized = ltrim($normalized, '.');

                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        $extraSettings = is_array(SiteSetting::current()->extra_settings) ? SiteSetting::current()->extra_settings : [];
        $utmSiteDefault = trim((string) ($extraSettings['utm_site_default'] ?? ''));
        if ($utmSiteDefault !== '') {
            return $utmSiteDefault;
        }

        $host = trim((string) $request->getHost());

        if ($host !== '' && ! in_array(strtolower($host), ['localhost', '127.0.0.1'], true)) {
            return strtolower($host);
        }

        return 'admin.ileben.cl';
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function enrichProjectSalesforceId(array $fields): array
    {
        if (isset($fields['proyecto_salesforce_id']) && trim((string) $fields['proyecto_salesforce_id']) !== '') {
            return $fields;
        }

        $projectName = $this->fieldValue($fields, ['nombre_proyecto', 'proyecto', 'project_name', 'proyecto_formulario', 'project']);

        if ($projectName === null) {
            return $fields;
        }

        if ($this->isValidSalesforceId($projectName)) {
            $fields['proyecto_salesforce_id'] = $projectName;

            return $fields;
        }

        $project = Proyecto::query()
            ->select(['id', 'salesforce_id', 'name', 'slug'])
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($projectName)])
            ->first();

        if ($project === null) {
            $slug = Str::of($projectName)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
            $project = Proyecto::query()
                ->select(['id', 'salesforce_id', 'name', 'slug'])
                ->where('slug', $slug)
                ->first();
        }

        if ($project !== null && filled($project->salesforce_id)) {
            $fields['proyecto_salesforce_id'] = $project->salesforce_id;
        }

        return $fields;
    }

    private function isValidSalesforceId(string $value): bool
    {
        $normalized = trim($value);

        return $normalized !== '' && preg_match('/^[a-zA-Z0-9]{15,18}$/', $normalized) === 1;
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
