<?php

namespace App\Services\ProductionSync;

use App\Models\Asesor;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Support\FlowLogMatrix;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class ProductionSyncService
{
    /**
     * @return array{meta: array<string, mixed>, site_settings: array<string, mixed>, advisors: list<array<string, mixed>>, projects: list<array<string, mixed>>, plants: list<array<string, mixed>>}
     */
    public function fetchSnapshot(?string $baseUrl = null, ?string $token = null, ?string $authorizedUrl = null): array
    {
        $baseUrl = trim((string) ($baseUrl ?? config('services.production_sync.base_url', '')));
        $token = trim((string) ($token ?? config('services.production_sync.token', '')));
        $authorizedUrl = trim((string) ($authorizedUrl ?? config('services.production_sync.authorized_url', '')));

        if ($authorizedUrl === '') {
            $authorizedUrl = trim((string) config('app.url', '')) ?: 'http://127.0.0.1:8000';
        }

        $endpoint = rtrim($baseUrl, '/').'/api/v1/production-sync/export';

        if ($baseUrl === '' || $token === '') {
            FlowLogMatrix::write('production_sync.config_missing', 'ProductionSync: Configuración incompleta para export de snapshot', [
                'base_url_configured' => $baseUrl !== '',
                'token_configured' => $token !== '',
            ]);

            return [
                'meta' => [
                    'error' => 'Falta configurar PRODUCTION_SYNC_BASE_URL o PRODUCTION_SYNC_TOKEN.',
                ],
                'site_settings' => [],
                'advisors' => [],
                'projects' => [],
                'plants' => [],
            ];
        }

        $httpVerify = (bool) config('services.production_sync.http_verify', false);

        try {
            $pendingRequest = Http::acceptJson()
                ->withToken($token)
                ->withHeaders([
                    'X-Authorized-Url' => $authorizedUrl,
                ])
                ->timeout((int) config('services.production_sync.timeout', 120));

            if (! $httpVerify) {
                $pendingRequest = $pendingRequest->withoutVerifying();
            }

            $response = $pendingRequest->get($endpoint);
        } catch (Throwable $exception) {
            FlowLogMatrix::write('production_sync.network_error', 'ProductionSync: Error de red al obtener snapshot', [
                'endpoint' => $endpoint,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            $isTimeout = str_contains(strtolower($exception->getMessage()), 'timed out')
                || str_contains(strtolower($exception->getMessage()), 'timeout')
                || str_contains(strtolower($exception->getMessage()), 'curl error 28');

            $errorMessage = $isTimeout
                ? "Tiempo de espera agotado (timeout) al conectar con producción ({$endpoint})."
                : 'No se pudo obtener la sincronización de producción.';

            return [
                'meta' => [
                    'error' => $errorMessage,
                ],
                'site_settings' => [],
                'advisors' => [],
                'projects' => [],
                'plants' => [],
            ];
        }

        if (! $response->successful()) {
            FlowLogMatrix::write('production_sync.http_unsuccessful', 'ProductionSync: Respuesta no exitosa al obtener snapshot', [
                'endpoint' => $endpoint,
                'http_status' => $response->status(),
                'response_message' => (string) ($response->json('message') ?? ''),
            ]);

            return [
                'meta' => [
                    'error' => (string) ($response->json('message') ?? 'No se pudo obtener la sincronización de producción.'),
                ],
                'site_settings' => [],
                'advisors' => [],
                'projects' => [],
                'plants' => [],
            ];
        }

        /** @var array{meta?: array<string, mixed>, site_settings?: array<string, mixed>, advisors?: list<array<string, mixed>>, projects?: list<array<string, mixed>>, plants?: list<array<string, mixed>>} $payload */
        $payload = $response->json();

        return [
            'meta' => (array) ($payload['meta'] ?? []),
            'site_settings' => (array) ($payload['site_settings'] ?? []),
            'advisors' => array_values((array) ($payload['advisors'] ?? [])),
            'projects' => array_values((array) ($payload['projects'] ?? [])),
            'plants' => array_values((array) ($payload['plants'] ?? [])),
        ];
    }

    /**
     * @param  array{site_settings?: array<string, mixed>, advisors?: list<array<string, mixed>>, projects?: list<array<string, mixed>>, plants?: list<array<string, mixed>>}  $snapshot
     * @return array{site_settings: string, advisors: array{created:int, updated:int, skipped:int}, projects: array{created:int, updated:int, skipped:int}, plants: array{created:int, updated:int, skipped:int}}
     */
    public function syncSnapshot(string $syncId, array $snapshot, ProductionSyncProgressTracker $tracker): array
    {
        $siteSettingsStatus = 'skipped';
        $advisorsResult = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $projectsResult = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $plantsResult = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $siteSettingsPayload = (array) ($snapshot['site_settings'] ?? []);

        if ($siteSettingsPayload !== []) {
            $siteSettingsStatus = $this->syncSiteSettings($syncId, $siteSettingsPayload, $tracker);
        } else {
            $tracker->increment($syncId, 'skipped');
            $tracker->increment($syncId, 'processed');
            $tracker->addLog($syncId, 'No se recibió configuración de sitio para sincronizar.');
        }

        foreach ((array) ($snapshot['projects'] ?? []) as $projectPayload) {
            $status = $this->syncProject($syncId, (array) $projectPayload, $tracker);
            $projectsResult[$status]++;
        }

        foreach ((array) ($snapshot['advisors'] ?? []) as $advisorPayload) {
            $status = $this->syncAdvisor($syncId, (array) $advisorPayload, $tracker);
            $advisorsResult[$status]++;
        }

        foreach ((array) ($snapshot['plants'] ?? []) as $plantPayload) {
            $status = $this->syncPlant($syncId, (array) $plantPayload, $tracker);
            $plantsResult[$status]++;
        }

        return [
            'site_settings' => $siteSettingsStatus,
            'advisors' => $advisorsResult,
            'projects' => $projectsResult,
            'plants' => $plantsResult,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncSiteSettings(string $syncId, array $payload, ProductionSyncProgressTracker $tracker): string
    {
        $settings = SiteSetting::current();
        $attributes = Arr::only($payload, SiteSetting::syncableFields());

        if (array_key_exists('extra_settings', $payload) && is_array($payload['extra_settings'])) {
            $currentExtraSettings = is_array($settings->extra_settings) ? $settings->extra_settings : [];
            $incomingExtraSettings = $this->filterExtraSettings((array) $payload['extra_settings']);
            $attributes['extra_settings'] = array_replace_recursive($currentExtraSettings, $incomingExtraSettings);
        }

        $settings->fill($attributes);
        $settings->save();

        $tracker->increment($syncId, 'updated');
        $tracker->increment($syncId, 'processed');
        $tracker->addLog($syncId, 'Configuración del sitio actualizada desde producción.');

        return 'updated';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncProject(string $syncId, array $payload, ProductionSyncProgressTracker $tracker): string
    {
        $salesforceId = trim((string) ($payload['salesforce_id'] ?? ''));

        if ($salesforceId === '') {
            $tracker->increment($syncId, 'skipped');
            $tracker->increment($syncId, 'processed');
            $tracker->addLog($syncId, 'Proyecto omitido: falta salesforce_id.');

            return 'skipped';
        }

        $attributes = Arr::only($payload, Proyecto::syncableFields());
        $existing = Proyecto::query()->where('salesforce_id', $salesforceId)->first();
        $status = $existing === null ? 'created' : 'updated';

        Proyecto::query()->updateOrCreate(
            ['salesforce_id' => $salesforceId],
            $attributes,
        );

        $tracker->increment($syncId, $status);
        $tracker->increment($syncId, 'processed');
        $tracker->addLog($syncId, sprintf(
            'Proyecto %s: %s.',
            $salesforceId,
            $status === 'created' ? 'creado' : 'actualizado'
        ));

        return $status;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncAdvisor(string $syncId, array $payload, ProductionSyncProgressTracker $tracker): string
    {
        $salesforceId = trim((string) ($payload['salesforce_id'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));

        if ($salesforceId === '' && $email === '') {
            $tracker->increment($syncId, 'skipped');
            $tracker->increment($syncId, 'processed');
            $tracker->addLog($syncId, 'Asesor omitido: falta salesforce_id y email.');

            return 'skipped';
        }

        $attributes = Arr::only($payload, Asesor::syncableFields());

        $existing = null;
        if ($salesforceId !== '') {
            $existing = Asesor::query()->where('salesforce_id', $salesforceId)->first();
        }
        if (! $existing && $email !== '') {
            $existing = Asesor::query()->where('email', $email)->first();
        }

        $status = $existing === null ? 'created' : 'updated';

        if ($existing) {
            $existing->update($attributes);
            $advisor = $existing;
        } else {
            $advisor = Asesor::query()->create($attributes);
        }

        if (array_key_exists('proyectos_salesforce_ids', $payload) && is_array($payload['proyectos_salesforce_ids'])) {
            $sfIds = array_values(array_filter(array_map('strval', $payload['proyectos_salesforce_ids'])));
            if ($sfIds !== []) {
                $proyectoIds = Proyecto::query()
                    ->whereIn('salesforce_id', $sfIds)
                    ->pluck('id')
                    ->all();
                $advisor->proyectos()->sync($proyectoIds);
            } else {
                $advisor->proyectos()->sync([]);
            }
        }

        $tracker->increment($syncId, $status);
        $tracker->increment($syncId, 'processed');
        $tracker->addLog($syncId, sprintf(
            'Asesor %s: %s.',
            $salesforceId !== '' ? $salesforceId : $email,
            $status === 'created' ? 'creado' : 'actualizado'
        ));

        return $status;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncPlant(string $syncId, array $payload, ProductionSyncProgressTracker $tracker): string
    {
        $salesforceProductId = trim((string) ($payload['salesforce_product_id'] ?? ''));

        if ($salesforceProductId === '') {
            $tracker->increment($syncId, 'skipped');
            $tracker->increment($syncId, 'processed');
            $tracker->addLog($syncId, 'Planta omitida: falta salesforce_product_id.');

            return 'skipped';
        }

        $attributes = Arr::only($payload, Plant::syncableFields());
        $attributes['product_code'] = trim((string) ($attributes['product_code'] ?? ''));

        if ($attributes['product_code'] === '') {
            $attributes['product_code'] = $salesforceProductId;
        }

        if (array_key_exists('asesor_salesforce_id', $payload)) {
            $sfAsesorId = trim((string) ($payload['asesor_salesforce_id'] ?? ''));
            if ($sfAsesorId !== '') {
                $asesorId = Asesor::query()->where('salesforce_id', $sfAsesorId)->value('id');
                $attributes['asesor_id'] = $asesorId ?: null;
            } else {
                $attributes['asesor_id'] = null;
            }
        }

        $existing = Plant::query()->where('salesforce_product_id', $salesforceProductId)->first();
        $status = $existing === null ? 'created' : 'updated';

        Plant::query()->updateOrCreate(
            ['salesforce_product_id' => $salesforceProductId],
            $attributes,
        );

        $tracker->increment($syncId, $status);
        $tracker->increment($syncId, 'processed');
        $tracker->addLog($syncId, sprintf(
            'Planta %s: %s.',
            $salesforceProductId,
            $status === 'created' ? 'creada' : 'actualizada'
        ));

        return $status;
    }

    /**
     * @param  array<string, mixed>  $extraSettings
     * @return array<string, mixed>
     */
    private function filterExtraSettings(array $extraSettings): array
    {
        $filtered = [];

        foreach ($extraSettings as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if ($normalizedKey === 'salesforce_oauth') {
                continue;
            }

            if (str_contains($normalizedKey, 'url') || str_ends_with($normalizedKey, '_id')) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->filterExtraSettings($value);

                if ($nested !== []) {
                    $filtered[$key] = $nested;
                }

                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }
}
