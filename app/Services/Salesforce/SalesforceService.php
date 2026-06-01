<?php

namespace App\Services\Salesforce;

use App\Exceptions\SalesforceTokenExpiredException;
use App\Models\Broker;
use App\Models\Proyecto;
use App\Models\SalesforceOpportunity;
use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;

class SalesforceService
{
    private const LEAD_CREATABLE_FIELDS_CACHE_KEY = 'salesforce:lead:creatable-fields';

    private const LEAD_CREATABLE_FIELDS_CACHE_TTL_SECONDS = 86400;

    private const LEAD_UNAVAILABLE_FIELDS_CACHE_KEY = 'salesforce:lead:unavailable-fields';

    private const LEAD_UNAVAILABLE_FIELDS_CACHE_TTL_SECONDS = 86400;

    /**
     * Tiempo de caché predeterminado para consultas SOQL (en segundos)
     */
    protected int $defaultCacheTtl = 900; // 15 minutos

    /**
     * Ejecutar una consulta SOQL con caché automático
     */
    public function query(string $soql, ?int $cacheTtl = null): array
    {
        $cacheKey = $this->generateCacheKey($soql);
        $ttl = $cacheTtl ?? $this->defaultCacheTtl;

        return Cache::remember($cacheKey, $ttl, function () use ($soql) {
            try {
                $result = Forrest::query($soql);

                return $result['records'] ?? [];
            } catch (\Throwable $e) {
                if ($this->isRefreshTokenExpiredException($e)) {
                    Log::critical('Salesforce: Token OAuth inválido o expirado durante query SOQL. Se requiere reconexión manual.', [
                        ...$this->oauthTokenFailureContext($e, 'query', [
                            'soql_hash' => md5($soql),
                        ]),
                    ]);

                    throw new SalesforceTokenExpiredException(
                        'Salesforce OAuth token expired or revoked. Manual reconnection required.',
                        0,
                        $e
                    );
                }

                // Re-autenticar si el token expiró o no hay recursos disponibles
                Log::debug('Salesforce: Re-autenticando debido a: ' . $e->getMessage());
                $this->authenticate();
                $result = Forrest::query($soql);

                return $result['records'] ?? [];
            }
        });
    }

    /**
     * Ejecuta una consulta SOQL sin cache para diagnostico/manual query runner.
     *
     * @return array{query:string,limit:int,total_size:int,done:bool,records:array<int,array<string,mixed>>}
     */
    public function runSoqlWithoutCache(string $soql): array
    {
        $normalizedSoql = trim($soql);

        if (! str_starts_with(strtoupper($normalizedSoql), 'SELECT ')) {
            throw new InvalidArgumentException('La consulta SOQL debe iniciar con SELECT.');
        }

        if (preg_match('/\bLIMIT\s+(\d+)\b/i', $normalizedSoql, $matches) !== 1) {
            throw new InvalidArgumentException('La consulta SOQL debe incluir LIMIT para evitar ejecuciones sin tope.');
        }

        $limit = (int) ($matches[1] ?? 0);
        if ($limit <= 0) {
            throw new InvalidArgumentException('La consulta SOQL debe incluir LIMIT mayor a cero.');
        }

        try {
            $result = Forrest::query($normalizedSoql);
        } catch (\Throwable $exception) {
            Log::warning('Salesforce: runSoqlWithoutCache fallo en primer intento; se reautentica y reintenta.', [
                'error' => $exception->getMessage(),
                'soql_hash' => md5($normalizedSoql),
            ]);

            $this->authenticate();
            $result = Forrest::query($normalizedSoql);
        }

        $records = is_array($result['records'] ?? null) ? $result['records'] : [];

        return [
            'query' => $normalizedSoql,
            'limit' => $limit,
            'total_size' => (int) ($result['totalSize'] ?? count($records)),
            'done' => (bool) ($result['done'] ?? true),
            'records' => $records,
        ];
    }

    /**
     * Sincroniza snapshots de oportunidades desde Salesforce y actualiza métricas comerciales en brokers.
     *
     * @return array{success:bool,message:string,synced:int,created:int,updated:int,watermark:string|null}
     */
    public function syncOpportunitiesIncrementally(?Carbon $since = null, int $limit = 2000): array
    {
        $normalizedLimit = max(1, min($limit, 2000));
        $watermark = $since?->copy()->utc();

        if ($watermark === null) {
            $storedWatermark = data_get(SiteSetting::current()->extra_settings, 'salesforce_sync.opportunities_last_system_modstamp');

            if (is_string($storedWatermark) && trim($storedWatermark) !== '') {
                try {
                    $watermark = Carbon::parse($storedWatermark)->utc();
                } catch (\Throwable) {
                    $watermark = null;
                }
            }
        }

        $select = [
            'Id',
            'Name',
            'Broker__c',
            'Broker__r.Name',
            'Proyecto__c',
            'Proyecto__r.Name',
            'StageName',
            'ForecastCategoryName',
            'IsWon',
            'IsClosed',
            'IsDeleted',
            'IsPrivate',
            'CreatedDate',
            'LastModifiedDate',
            'SystemModstamp',
            'CloseDate',
            'Amount',
            'CurrencyIsoCode',
            'Probability',
            'AccountId',
            'ContactId',
            'OwnerId',
        ];

        $filters = ['Id != null'];
        if ($watermark instanceof Carbon) {
            $filters[] = "SystemModstamp > {$watermark->format('Y-m-d\\TH:i:s\\Z')}";
        }

        $soql = sprintf(
            'SELECT %s FROM Opportunity WHERE %s ORDER BY SystemModstamp ASC LIMIT %d',
            implode(', ', $select),
            implode(' AND ', $filters),
            $normalizedLimit
        );

        try {
            $response = Forrest::query($soql);
        } catch (\Throwable $exception) {
            Log::warning('Salesforce: fallo inicial al sincronizar oportunidades, reautenticando.', [
                'error' => $exception->getMessage(),
                'soql_hash' => md5($soql),
            ]);

            $this->authenticate();
            $response = Forrest::query($soql);
        }

        $records = is_array($response['records'] ?? null) ? $response['records'] : [];

        $created = 0;
        $updated = 0;
        $lastSystemModstamp = $watermark;

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $salesforceId = trim((string) ($record['Id'] ?? ''));
            if ($salesforceId === '') {
                continue;
            }

            $brokerSalesforceId = $this->normalizeSalesforceId($record['Broker__c'] ?? null);
            $proyectoSalesforceId = $this->normalizeSalesforceId($record['Proyecto__c'] ?? null);

            $brokerId = null;
            if ($brokerSalesforceId !== null) {
                $brokerId = Broker::query()
                    ->where('salesforce_id', $brokerSalesforceId)
                    ->value('id');
            }

            $proyectoId = null;
            if ($proyectoSalesforceId !== null) {
                $proyectoId = Proyecto::query()
                    ->where('salesforce_id', $proyectoSalesforceId)
                    ->value('id');
            }

            $snapshot = SalesforceOpportunity::query()->firstWhere('salesforce_id', $salesforceId);

            $payload = [
                'broker_id' => $brokerId,
                'proyecto_id' => $proyectoId,
                'broker_salesforce_id' => $brokerSalesforceId,
                'proyecto_salesforce_id' => $proyectoSalesforceId,
                'account_salesforce_id' => $this->normalizeSalesforceId($record['AccountId'] ?? null),
                'contact_salesforce_id' => $this->normalizeSalesforceId($record['ContactId'] ?? null),
                'owner_salesforce_id' => $this->normalizeSalesforceId($record['OwnerId'] ?? null),
                'name' => (string) ($record['Name'] ?? ''),
                'broker_name' => data_get($record, 'Broker__r.Name'),
                'proyecto_name' => data_get($record, 'Proyecto__r.Name'),
                'stage_name' => data_get($record, 'StageName'),
                'forecast_category_name' => data_get($record, 'ForecastCategoryName'),
                'currency_iso_code' => data_get($record, 'CurrencyIsoCode'),
                'amount' => is_numeric($record['Amount'] ?? null) ? (float) $record['Amount'] : null,
                'probability' => is_numeric($record['Probability'] ?? null) ? (float) $record['Probability'] : null,
                'is_closed' => (bool) ($record['IsClosed'] ?? false),
                'is_won' => (bool) ($record['IsWon'] ?? false),
                'is_deleted' => (bool) ($record['IsDeleted'] ?? false),
                'is_private' => (bool) ($record['IsPrivate'] ?? false),
                'close_date' => $this->parseDateValue($record['CloseDate'] ?? null),
                'salesforce_created_at' => $this->parseDateTimeValue($record['CreatedDate'] ?? null),
                'salesforce_last_modified_at' => $this->parseDateTimeValue($record['LastModifiedDate'] ?? null),
                'salesforce_system_modstamp' => $this->parseDateTimeValue($record['SystemModstamp'] ?? null),
                'synced_at' => now(),
                'payload' => $record,
            ];

            if ($snapshot instanceof SalesforceOpportunity) {
                $snapshot->update($payload);
                $updated++;
            } else {
                SalesforceOpportunity::query()->create([
                    'salesforce_id' => $salesforceId,
                    ...$payload,
                ]);
                $created++;
            }

            $recordModstamp = $this->parseDateTimeValue($record['SystemModstamp'] ?? null);
            if ($recordModstamp instanceof Carbon && ($lastSystemModstamp === null || $recordModstamp->gt($lastSystemModstamp))) {
                $lastSystemModstamp = $recordModstamp;
            }
        }

        if ($lastSystemModstamp instanceof Carbon) {
            $this->storeOpportunitySyncWatermark($lastSystemModstamp);
        }

        $this->syncBrokerCommercialMetricsFromSnapshots();

        $synced = $created + $updated;

        return [
            'success' => true,
            'message' => "Sincronizacion completada. Registros procesados: {$synced}.",
            'synced' => $synced,
            'created' => $created,
            'updated' => $updated,
            'watermark' => $lastSystemModstamp?->toIso8601String(),
        ];
    }

    public function syncBrokerCommercialMetricsFromSnapshots(): void
    {
        $brokers = Broker::query()->get(['id', 'salesforce_id', 'display_name']);
        $opportunities = SalesforceOpportunity::query()
            ->where('is_deleted', false)
            ->where('is_private', false)
            ->get();

        $windowStart = now()->subDays(30);

        foreach ($brokers as $broker) {
            $resolvedName = trim((string) ($broker->display_name ?? ''));
            $query = $opportunities->filter(function (SalesforceOpportunity $opportunity) use ($broker, $resolvedName): bool {
                $matchesBySalesforceId = filled($broker->salesforce_id)
                    && $opportunity->broker_salesforce_id === $broker->salesforce_id;

                $matchesByName = $resolvedName !== ''
                    && strcasecmp((string) ($opportunity->broker_name ?? ''), $resolvedName) === 0;

                return $matchesBySalesforceId || $matchesByName;
            })->values();

            $total = $query->count();
            $open = $query->where('is_closed', false)->count();
            $won = $query->where('is_won', true)->count();
            $lost = $query->where('is_closed', true)->where('is_won', false)->count();

            $windowed = $query->filter(function (SalesforceOpportunity $opportunity) use ($windowStart): bool {
                return $opportunity->salesforce_created_at instanceof Carbon
                    && $opportunity->salesforce_created_at->gte($windowStart);
            })->values();

            $total30 = $windowed->count();
            $won30 = $windowed->where('is_won', true)->count();
            $pipelineAmount30 = (float) $windowed->sum(fn(SalesforceOpportunity $opportunity): float => (float) ($opportunity->amount ?? 0));
            $wonAmount30 = (float) $windowed->where('is_won', true)->sum(fn(SalesforceOpportunity $opportunity): float => (float) ($opportunity->amount ?? 0));

            $lastOpportunity = $query
                ->sortByDesc(fn(SalesforceOpportunity $opportunity) => $opportunity->salesforce_system_modstamp?->timestamp ?? 0)
                ->first();

            $projectsPortfolio = $query
                ->pluck('proyecto_name')
                ->filter(fn(mixed $name): bool => is_string($name) && trim($name) !== '')
                ->map(fn(string $name): string => trim($name))
                ->unique()
                ->sort()
                ->values()
                ->all();

            $broker->update([
                'opportunities_total' => $total,
                'opportunities_open' => $open,
                'opportunities_won' => $won,
                'opportunities_lost' => $lost,
                'opportunities_total_30d' => $total30,
                'opportunities_won_30d' => $won30,
                'closure_rate_30d' => $total30 > 0 ? round(($won30 / $total30) * 100, 2) : null,
                'pipeline_amount_30d' => round($pipelineAmount30, 2),
                'won_amount_30d' => round($wonAmount30, 2),
                'last_opportunity_at' => $lastOpportunity?->salesforce_system_modstamp,
                'last_stage_name' => $lastOpportunity?->stage_name,
                'projects_portfolio' => $projectsPortfolio,
                'salesforce_synced_at' => now(),
            ]);
        }
    }

    private function parseDateValue(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateTimeValue(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function storeOpportunitySyncWatermark(Carbon $watermark): void
    {
        $settings = SiteSetting::current();
        $extraSettings = is_array($settings->extra_settings) ? $settings->extra_settings : [];

        data_set(
            $extraSettings,
            'salesforce_sync.opportunities_last_system_modstamp',
            $watermark->toIso8601String()
        );

        $settings->update([
            'extra_settings' => $extraSettings,
        ]);
    }

    /**
     * Crear un Case en Salesforce.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCase(array $payload): array
    {
        Log::debug('Salesforce: Enviando solicitud de creación de Case', [
            'subject' => $payload['Subject'] ?? null,
            'record_type_id' => $payload['RecordTypeId'] ?? null,
            'origin' => $payload['Origin'] ?? null,
            'payload_keys' => array_keys($payload),
        ]);

        try {
            $result = Forrest::sobjects('Case', [
                'method' => 'post',
                'body' => $payload,
            ]);

            $response = is_array($result) ? $result : [];

            Log::debug('Salesforce: Respuesta creación de Case', [
                'case_id' => $response['id'] ?? $response['Id'] ?? null,
                'success' => $response['success'] ?? null,
                'errors' => $response['errors'] ?? null,
                'subject' => $payload['Subject'] ?? null,
                'response' => $response,
            ]);

            return $response;
        } catch (\Throwable $firstException) {
            if ($this->isRefreshTokenExpiredException($firstException)) {
                Log::critical('Salesforce: Token OAuth inválido o expirado al crear Case. Se requiere reconexión manual.', [
                    ...$this->oauthTokenFailureContext($firstException, 'create_case', [
                        'subject' => $payload['Subject'] ?? null,
                    ]),
                ]);

                throw new SalesforceTokenExpiredException(
                    'Salesforce OAuth token expired or revoked. Manual reconnection required.',
                    0,
                    $firstException
                );
            }

            Log::warning('Salesforce: Error creando Case, se intentará re-autenticación', [
                'error' => $firstException->getMessage(),
                'subject' => $payload['Subject'] ?? null,
            ]);

            $this->authenticate();

            try {
                $result = Forrest::sobjects('Case', [
                    'method' => 'post',
                    'body' => $payload,
                ]);

                $response = is_array($result) ? $result : [];

                Log::debug('Salesforce: Respuesta creación de Case tras re-autenticación', [
                    'case_id' => $response['id'] ?? $response['Id'] ?? null,
                    'success' => $response['success'] ?? null,
                    'errors' => $response['errors'] ?? null,
                    'subject' => $payload['Subject'] ?? null,
                    'response' => $response,
                ]);

                return $response;
            } catch (\Throwable $secondException) {
                Log::error('Salesforce: Error creando Case tras re-autenticación', [
                    'error' => $secondException->getMessage(),
                    'subject' => $payload['Subject'] ?? null,
                ]);

                throw $secondException;
            }
        }
    }

    /**
     * Crear un Lead en Salesforce.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createLead(array $payload): array
    {
        $currentPayload = $this->sanitizeLeadPayloadWithCreatableFields($payload);
        $currentPayload = $this->sanitizeLeadPayloadWithKnownUnavailableFields($currentPayload);

        Log::debug('Salesforce: Enviando solicitud de creación de Lead', [
            'email' => $currentPayload['Email'] ?? null,
            'lead_source' => $currentPayload['LeadSource'] ?? null,
            'payload_keys' => array_keys($currentPayload),
        ]);

        try {
            $result = Forrest::sobjects('Lead', [
                'method' => 'post',
                'body' => $currentPayload,
            ]);

            $response = is_array($result) ? $result : [];

            Log::debug('Salesforce: Respuesta creación de Lead', [
                'lead_id' => $response['id'] ?? $response['Id'] ?? null,
                'success' => $response['success'] ?? null,
                'errors' => $response['errors'] ?? null,
                'response' => $response,
            ]);

            return $response;
        } catch (\Throwable $firstException) {
            if ($this->isRefreshTokenExpiredException($firstException)) {
                Log::critical('Salesforce: Token OAuth inválido o expirado al crear Lead. Se requiere reconexión manual.', [
                    ...$this->oauthTokenFailureContext($firstException, 'create_lead_first_attempt', [
                        'email' => $payload['Email'] ?? null,
                    ]),
                ]);

                throw new SalesforceTokenExpiredException(
                    'Salesforce OAuth token expired or revoked. Manual reconnection required.',
                    0,
                    $firstException
                );
            }

            $sanitized = $this->removeUnavailableLeadFields($currentPayload, $firstException);
            $currentPayload = $sanitized['payload'];

            if ($sanitized['removed_fields'] !== []) {
                $this->rememberUnavailableLeadFields($sanitized['removed_fields']);

                Log::warning('Salesforce: Campos removidos del payload de Lead, reintentando', [
                    'removed_fields' => $sanitized['removed_fields'],
                    'email' => $payload['Email'] ?? null,
                    'payload_keys' => array_keys($currentPayload),
                ]);

                $result = Forrest::sobjects('Lead', [
                    'method' => 'post',
                    'body' => $currentPayload,
                ]);

                $response = is_array($result) ? $result : [];

                Log::debug('Salesforce: Respuesta creación de Lead tras remover campo inválido', [
                    'lead_id' => $response['id'] ?? $response['Id'] ?? null,
                    'success' => $response['success'] ?? null,
                    'errors' => $response['errors'] ?? null,
                    'response' => $response,
                    'removed_fields' => $sanitized['removed_fields'],
                ]);

                return $response;
            }

            $ownerFallback = $this->applyLeadOwnerFallbackOnFlowError($currentPayload, $firstException);
            $currentPayload = $ownerFallback['payload'];

            if ($ownerFallback['applied']) {
                Log::warning('Salesforce: Reintentando Lead por error de flujo con OwnerId forzado', [
                    'email' => $payload['Email'] ?? null,
                    'owner_id' => $ownerFallback['owner_id'],
                    'payload_keys' => array_keys($currentPayload),
                ]);

                $result = Forrest::sobjects('Lead', [
                    'method' => 'post',
                    'body' => $currentPayload,
                ]);

                $response = is_array($result) ? $result : [];

                Log::debug('Salesforce: Respuesta creación de Lead tras forzar OwnerId', [
                    'lead_id' => $response['id'] ?? $response['Id'] ?? null,
                    'success' => $response['success'] ?? null,
                    'errors' => $response['errors'] ?? null,
                    'response' => $response,
                    'owner_id' => $ownerFallback['owner_id'],
                ]);

                return $response;
            }

            Log::warning('Salesforce: Error creando Lead, se intentará re-autenticación', [
                'error' => $firstException->getMessage(),
                'email' => $payload['Email'] ?? null,
            ]);

            $this->authenticate();

            try {
                $result = Forrest::sobjects('Lead', [
                    'method' => 'post',
                    'body' => $currentPayload,
                ]);

                $response = is_array($result) ? $result : [];

                Log::debug('Salesforce: Respuesta creación de Lead tras re-autenticación', [
                    'lead_id' => $response['id'] ?? $response['Id'] ?? null,
                    'success' => $response['success'] ?? null,
                    'errors' => $response['errors'] ?? null,
                    'response' => $response,
                ]);

                return $response;
            } catch (\Throwable $secondException) {
                if ($this->isRefreshTokenExpiredException($secondException)) {
                    Log::critical('Salesforce: Token OAuth inválido o expirado tras re-auth al crear Lead. Se requiere reconexión manual.', [
                        ...$this->oauthTokenFailureContext($secondException, 'create_lead_after_reauth', [
                            'email' => $payload['Email'] ?? null,
                        ]),
                    ]);

                    throw new SalesforceTokenExpiredException(
                        'Salesforce OAuth token expired or revoked. Manual reconnection required.',
                        0,
                        $secondException
                    );
                }

                $sanitized = $this->removeUnavailableLeadFields($currentPayload, $secondException);
                $currentPayload = $sanitized['payload'];

                if ($sanitized['removed_fields'] !== []) {
                    $this->rememberUnavailableLeadFields($sanitized['removed_fields']);

                    Log::warning('Salesforce: Campos removidos tras re-auth, reintentando Lead', [
                        'removed_fields' => $sanitized['removed_fields'],
                        'email' => $payload['Email'] ?? null,
                        'payload_keys' => array_keys($currentPayload),
                    ]);

                    $result = Forrest::sobjects('Lead', [
                        'method' => 'post',
                        'body' => $currentPayload,
                    ]);

                    $response = is_array($result) ? $result : [];

                    Log::debug('Salesforce: Respuesta creación de Lead tras re-auth y remover campo inválido', [
                        'lead_id' => $response['id'] ?? $response['Id'] ?? null,
                        'success' => $response['success'] ?? null,
                        'errors' => $response['errors'] ?? null,
                        'response' => $response,
                        'removed_fields' => $sanitized['removed_fields'],
                    ]);

                    return $response;
                }

                $ownerFallback = $this->applyLeadOwnerFallbackOnFlowError($currentPayload, $secondException);
                $currentPayload = $ownerFallback['payload'];

                if ($ownerFallback['applied']) {
                    Log::warning('Salesforce: Reintentando Lead tras re-auth por error de flujo con OwnerId forzado', [
                        'email' => $payload['Email'] ?? null,
                        'owner_id' => $ownerFallback['owner_id'],
                        'payload_keys' => array_keys($currentPayload),
                    ]);

                    $result = Forrest::sobjects('Lead', [
                        'method' => 'post',
                        'body' => $currentPayload,
                    ]);

                    $response = is_array($result) ? $result : [];

                    Log::debug('Salesforce: Respuesta creación de Lead tras re-auth y OwnerId forzado', [
                        'lead_id' => $response['id'] ?? $response['Id'] ?? null,
                        'success' => $response['success'] ?? null,
                        'errors' => $response['errors'] ?? null,
                        'response' => $response,
                        'owner_id' => $ownerFallback['owner_id'],
                    ]);

                    return $response;
                }

                Log::error('Salesforce: Error creando Lead tras re-autenticación', [
                    'error' => $secondException->getMessage(),
                    'email' => $payload['Email'] ?? null,
                ]);

                throw $secondException;
            }
        }
    }

    private function extractInvalidLeadField(\Throwable $exception): ?string
    {
        $message = $exception->getMessage();

        if (preg_match("/No such column '([^']+)' on sobject of type Lead/i", $message, $matches) !== 1) {
            return null;
        }

        $field = trim((string) ($matches[1] ?? ''));

        return $field !== '' ? $field : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeLeadPayloadWithCreatableFields(array $payload): array
    {
        $creatableFields = $this->leadCreatableFieldNames();

        if ($creatableFields === []) {
            return $payload;
        }

        $allowedFields = array_fill_keys($creatableFields, true);
        $removedFields = [];

        foreach (array_keys($payload) as $field) {
            if (! is_string($field) || array_key_exists($field, $allowedFields)) {
                continue;
            }

            unset($payload[$field]);
            $removedFields[] = $field;
        }

        if ($removedFields !== []) {
            Log::warning('Salesforce: Campos removidos del payload de Lead por metadata describe', [
                'removed_fields' => array_values(array_unique($removedFields)),
            ]);
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    private function leadCreatableFieldNames(): array
    {
        $cached = Cache::get(self::LEAD_CREATABLE_FIELDS_CACHE_KEY);

        if (is_array($cached) && $cached !== []) {
            return array_values(array_unique(array_filter(array_map(
                static fn(mixed $field): string => trim((string) $field),
                $cached
            ), static fn(string $field): bool => $field !== '')));
        }

        try {
            $describe = Forrest::describe('Lead');
            $fields = is_array($describe['fields'] ?? null) ? $describe['fields'] : [];

            $creatableFields = [];

            foreach ($fields as $field) {
                if (! is_array($field)) {
                    continue;
                }

                if (($field['createable'] ?? false) !== true) {
                    continue;
                }

                $name = trim((string) ($field['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $creatableFields[] = $name;
            }

            $creatableFields = array_values(array_unique($creatableFields));

            if ($creatableFields !== []) {
                Cache::put(
                    self::LEAD_CREATABLE_FIELDS_CACHE_KEY,
                    $creatableFields,
                    now()->addSeconds(self::LEAD_CREATABLE_FIELDS_CACHE_TTL_SECONDS)
                );
            }

            return $creatableFields;
        } catch (\Throwable $exception) {
            Log::debug('Salesforce: No se pudo obtener describe de Lead, se omite filtro preventivo', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{payload: array<string, mixed>, removed_fields: list<string>}
     */
    private function removeUnavailableLeadFields(array $payload, \Throwable $exception): array
    {
        $candidateFields = [];

        $invalidField = $this->extractInvalidLeadField($exception);
        if ($invalidField !== null) {
            $candidateFields[] = $invalidField;
        }

        $candidateFields = array_merge($candidateFields, $this->extractNonWritableLeadFields($exception));
        $candidateFields = array_values(array_unique(array_filter(array_map(
            static fn(string $field): string => trim($field),
            $candidateFields
        ), static fn(string $field): bool => $field !== '')));

        $removedFields = [];

        foreach ($candidateFields as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            unset($payload[$field]);
            $removedFields[] = $field;
        }

        return [
            'payload' => $payload,
            'removed_fields' => $removedFields,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeLeadPayloadWithKnownUnavailableFields(array $payload): array
    {
        $knownUnavailableFields = $this->knownUnavailableLeadFields();

        if ($knownUnavailableFields === []) {
            return $payload;
        }

        foreach ($knownUnavailableFields as $field) {
            unset($payload[$field]);
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    private function knownUnavailableLeadFields(): array
    {
        $cached = Cache::get(self::LEAD_UNAVAILABLE_FIELDS_CACHE_KEY, []);

        if (! is_array($cached)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn(mixed $field): string => trim((string) $field),
            $cached
        ), static fn(string $field): bool => $field !== '')));
    }

    /**
     * @param  list<string>  $fields
     */
    private function rememberUnavailableLeadFields(array $fields): void
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn(string $field): string => trim($field),
            $fields
        ), static fn(string $field): bool => $field !== '')));

        if ($normalized === []) {
            return;
        }

        $merged = array_values(array_unique(array_merge(
            $this->knownUnavailableLeadFields(),
            $normalized
        )));

        Cache::put(
            self::LEAD_UNAVAILABLE_FIELDS_CACHE_KEY,
            $merged,
            now()->addSeconds(self::LEAD_UNAVAILABLE_FIELDS_CACHE_TTL_SECONDS)
        );
    }

    /**
     * @return list<string>
     */
    private function extractNonWritableLeadFields(\Throwable $exception): array
    {
        if (! method_exists($exception, 'getResponse')) {
            return [];
        }

        $response = $exception->getResponse();

        if (! $response) {
            return [];
        }

        $decodedBody = json_decode((string) $response->getBody(), true);

        if (! is_array($decodedBody)) {
            return [];
        }

        $fields = [];

        foreach ($decodedBody as $errorItem) {
            if (! is_array($errorItem)) {
                continue;
            }

            if (($errorItem['errorCode'] ?? null) !== 'INVALID_FIELD_FOR_INSERT_UPDATE') {
                continue;
            }

            $errorFields = $errorItem['fields'] ?? [];

            if (! is_array($errorFields)) {
                continue;
            }

            foreach ($errorFields as $errorField) {
                $fields[] = trim((string) $errorField);
            }
        }

        return array_values(array_unique(array_filter($fields, static fn(string $field): bool => $field !== '')));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{payload: array<string, mixed>, applied: bool, owner_id: string|null}
     */
    private function applyLeadOwnerFallbackOnFlowError(array $payload, \Throwable $exception): array
    {
        if (! $this->isLeadFlowOwnerBlankError($exception)) {
            return [
                'payload' => $payload,
                'applied' => false,
                'owner_id' => null,
            ];
        }

        $currentOwnerId = $this->normalizeSalesforceId($payload['OwnerId'] ?? null);

        if ($currentOwnerId !== null) {
            return [
                'payload' => $payload,
                'applied' => false,
                'owner_id' => $currentOwnerId,
            ];
        }

        $configuredOwnerId = $this->configuredLeadOwnerId();

        if ($configuredOwnerId === null) {
            return [
                'payload' => $payload,
                'applied' => false,
                'owner_id' => null,
            ];
        }

        $payload['OwnerId'] = $configuredOwnerId;

        return [
            'payload' => $payload,
            'applied' => true,
            'owner_id' => $configuredOwnerId,
        ];
    }

    private function configuredLeadOwnerId(): ?string
    {
        $leadOwnerId = $this->normalizeSalesforceId(config('services.salesforce.lead_owner_id'));

        if ($leadOwnerId !== null) {
            return $leadOwnerId;
        }

        return $this->normalizeSalesforceId(config('services.salesforce.case_owner_id'));
    }

    private function normalizeSalesforceId(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^[a-zA-Z0-9]{15,18}$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    private function isLeadFlowOwnerBlankError(\Throwable $exception): bool
    {
        foreach ($this->extractSalesforceErrorItems($exception) as $errorItem) {
            $errorCode = (string) ($errorItem['errorCode'] ?? '');

            if ($errorCode !== 'CANNOT_EXECUTE_FLOW_TRIGGER') {
                continue;
            }

            $message = strtolower((string) ($errorItem['message'] ?? ''));

            if (preg_match('/(owner|propietario).*(blank|en blanco)/u', $message) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractSalesforceErrorItems(\Throwable $exception): array
    {
        if (! method_exists($exception, 'getResponse')) {
            return [];
        }

        $response = $exception->getResponse();

        if (! $response) {
            return [];
        }

        $decodedBody = json_decode((string) $response->getBody(), true);

        if (! is_array($decodedBody)) {
            return [];
        }

        $errors = [];

        foreach ($decodedBody as $errorItem) {
            if (! is_array($errorItem)) {
                continue;
            }

            $errors[] = $errorItem;
        }

        return $errors;
    }

    /**
     * Autenticar con Salesforce (útil para forzar refresh)
     */
    public function authenticate(): void
    {
        Log::debug('Salesforce: Iniciando autenticación...');
        try {
            Forrest::authenticate();
            Log::debug('Salesforce: Autenticación exitosa');
        } catch (\Exception $e) {
            if ($this->isRefreshTokenExpiredException($e)) {
                Log::critical('Salesforce: OAuth devolvió invalid_grant durante authenticate(). Se requiere reconexión manual.', [
                    ...$this->oauthTokenFailureContext($e, 'authenticate'),
                ]);

                throw new SalesforceTokenExpiredException(
                    'Salesforce OAuth token expired or revoked. Manual reconnection required.',
                    0,
                    $e
                );
            }

            Log::error('Salesforce: Error en autenticación - ' . $e->getMessage());
            throw $e;
        }
    }

    private function isRefreshTokenExpiredException(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'invalid_grant') && str_contains($message, 'expired access/refresh token')) {
            return true;
        }

        $oauthPayload = $this->extractOAuthErrorPayload($exception);

        if ($oauthPayload === []) {
            return false;
        }

        $error = strtolower(trim((string) ($oauthPayload['error'] ?? '')));
        $errorDescription = strtolower(trim((string) ($oauthPayload['error_description'] ?? '')));

        return $error === 'invalid_grant' && str_contains($errorDescription, 'expired access/refresh token');
    }

    /**
     * @param  array<string, mixed>  $extraContext
     * @return array<string, mixed>
     */
    private function oauthTokenFailureContext(\Throwable $exception, string $operation, array $extraContext = []): array
    {
        $oauthPayload = $this->extractOAuthErrorPayload($exception);

        $hasToken = null;

        try {
            $hasToken = Forrest::hasToken();
        } catch (\Throwable) {
            $hasToken = null;
        }

        $extraSettings = SiteSetting::current()->extra_settings;
        $oauthMetadata = is_array($extraSettings)
            ? data_get($extraSettings, 'salesforce_oauth', [])
            : [];

        $error = strtolower(trim((string) ($oauthPayload['error'] ?? '')));
        $errorDescription = strtolower(trim((string) ($oauthPayload['error_description'] ?? '')));

        return array_merge([
            'operation' => $operation,
            'error' => $exception->getMessage(),
            'oauth_error' => $error !== '' ? $error : null,
            'oauth_error_description' => $errorDescription !== '' ? $errorDescription : null,
            'oauth_failure_reason' => $this->classifyOAuthFailureReason($error, $errorDescription),
            'forrest_has_token' => $hasToken,
            'auth_method' => (string) config('forrest.authentication', ''),
            'oauth_scope_configured' => (string) config('services.salesforce.oauth_scope', ''),
            'oauth_prompt_configured' => (string) config('services.salesforce.oauth_prompt', ''),
            'login_url' => (string) config('services.salesforce.login_url', ''),
            'oauth_connected_flag' => data_get($oauthMetadata, 'connected'),
            'oauth_last_connected_at' => data_get($oauthMetadata, 'last_connected_at'),
            'oauth_last_disconnected_at' => data_get($oauthMetadata, 'last_disconnected_at'),
            'oauth_last_error' => data_get($oauthMetadata, 'last_error'),
        ], $extraContext);
    }

    private function classifyOAuthFailureReason(string $error, string $errorDescription): string
    {
        if ($error !== 'invalid_grant') {
            return 'non_invalid_grant';
        }

        if (str_contains($errorDescription, 'expired access/refresh token')) {
            return 'refresh_token_expired_or_revoked';
        }

        if (str_contains($errorDescription, 'authentication failure')) {
            return 'authentication_failure';
        }

        if (str_contains($errorDescription, 'invalid authorization code')) {
            return 'invalid_authorization_code';
        }

        if ($errorDescription === '') {
            return 'invalid_grant_without_description';
        }

        return 'invalid_grant_other';
    }

    /**
     * @return array<string, mixed>
     */
    private function extractOAuthErrorPayload(\Throwable $exception): array
    {
        if (! method_exists($exception, 'getResponse')) {
            return [];
        }

        $response = $exception->getResponse();

        if (! $response) {
            return [];
        }

        $decodedBody = json_decode((string) $response->getBody(), true);

        if (! is_array($decodedBody)) {
            return [];
        }

        return $decodedBody;
    }

    /**
     * Generar clave de caché basada en la consulta SOQL
     */
    protected function generateCacheKey(string $soql): string
    {
        return 'salesforce:soql:' . md5($soql);
    }

    /**
     * Limpiar todo el caché de Salesforce
     */
    public function clearCache(): void
    {
        Cache::flush(); // En producción, usar tags para ser más específico
    }

    /**
     * Obtener plantas desde Product2 disponibles
     * Estructura: Departamentos activos con estado "Disponible"
     *
     * @return array Array de plantas con estructura:
     *               [
     *               'id' => string,
     *               'name' => string,
     *               'product_code' => string,
     *               'orientacion' => string,
     *               'modelo_name' => string|null,
     *               'modelo_programa' => string|null,
     *               'programa' => string,
     *               'programa2' => string,
     *               'piso' => string,
     *               'precio_base' => float,
     *               'precio_lista' => float,
     *               'porcentaje_maximo_unidad' => float,
     *               'superficie_total_principal' => float,
     *               'superficie_interior' => float,
     *               'superficie_util' => float,
     *               'superficie_terraza' => float,
     *               ]
     */
    /**
     * @param  list<string>|null  $projectSalesforceIds
     */
    public function findPlants(?int $cacheTtl = null, ?array $projectSalesforceIds = null): array
    {
        $productTypes = $this->getConfiguredPlantProductTypes();
        $productTypesInClause = implode(',', array_map(
            static fn(string $type): string => "'" . str_replace("'", "\\'", $type) . "'",
            $productTypes
        ));
        $projectIds = $this->normalizeSalesforceIdList($projectSalesforceIds ?? []);

        if ($projectIds === []) {
            return [];
        }

        $projectIdsInClause = implode(',', array_map(
            static fn(string $id): string => "'" . str_replace("'", "\\'", $id) . "'",
            $projectIds
        ));

        // SOQL para obtener plantas desde Product2
        $soql = 'SELECT Id, Name, ProductCode, Orientacion2__c, Programa__c, Programa2__c, Modelo__r.Name, Modelo__r.Programa__c, Piso__c, '
            . 'Precio_Base__c, Precio_Lista__c, Porcentaje_maximo_de_unidad__c, '
            . 'Superficie_Total_Producto_Principal__c, Superficie_Interior__c, Superficie_Util__c, '
            . 'Superficie_Terraza__c, Proyecto__c, Tipo_Producto__c '
            . 'FROM Product2 '
            . "WHERE IsActive = true AND Estado__c = 'Disponible' AND Tipo_Producto__c IN ({$productTypesInClause}) AND Proyecto__c IN ({$projectIdsInClause}) "
            . 'ORDER BY Name '
            . 'LIMIT 1000';

        $ttl = $cacheTtl ?? $this->defaultCacheTtl;
        $cacheKey = $this->buildPlantsCacheKey($productTypes, $projectIds);

        return Cache::remember($cacheKey, $ttl, function () use ($soql) {
            try {
                $result = Forrest::query($soql);
                $entries = $result['records'] ?? [];

                // Transformar estructura Salesforce a formato más amigable
                return array_map(function ($entry) {
                    return [
                        'id' => $entry['Id'] ?? null,
                        'name' => $entry['Name'] ?? null,
                        'product_code' => $entry['ProductCode'] ?? null,
                        'tipo_producto' => $entry['Tipo_Producto__c'] ?? null,
                        'orientacion' => $entry['Orientacion2__c'] ?? null,
                        'modelo_name' => $entry['Modelo__r']['Name'] ?? null,
                        'modelo_programa' => $entry['Modelo__r']['Programa__c'] ?? null,
                        'programa' => $entry['Programa__c'] ?? null,
                        'programa2' => $entry['Programa2__c'] ?? null,
                        'piso' => $entry['Piso__c'] ?? null,
                        'precio_base' => (float) ($entry['Precio_Base__c'] ?? 0) ?: 0,
                        'precio_lista' => (float) ($entry['Precio_Lista__c'] ?? 0) ?: 0,
                        'porcentaje_maximo_unidad' => (float) ($entry['Porcentaje_maximo_de_unidad__c'] ?? 0) ?: 0,
                        'superficie_total_principal' => (float) ($entry['Superficie_Total_Producto_Principal__c'] ?? 0),
                        'superficie_interior' => (float) ($entry['Superficie_Interior__c'] ?? 0),
                        'superficie_util' => (float) ($entry['Superficie_Util__c'] ?? 0),
                        'superficie_terraza' => (float) ($entry['Superficie_Terraza__c'] ?? 0),
                        'proyecto_id' => $entry['Proyecto__c'] ?? null,
                    ];
                }, $entries);
            } catch (\Throwable $e) {
                // Re-autenticar si el token expiró o no hay recursos disponibles
                Log::debug('Salesforce: Re-autenticando plantas debido a: ' . $e->getMessage());
                $this->authenticate();
                $result = Forrest::query($soql);
                $entries = $result['records'] ?? [];

                return array_map(function ($entry) {
                    return [
                        'id' => $entry['Id'] ?? null,
                        'name' => $entry['Name'] ?? null,
                        'product_code' => $entry['ProductCode'] ?? null,
                        'tipo_producto' => $entry['Tipo_Producto__c'] ?? null,
                        'orientacion' => $entry['Orientacion2__c'] ?? null,
                        'modelo_name' => $entry['Modelo__r']['Name'] ?? null,
                        'modelo_programa' => $entry['Modelo__r']['Programa__c'] ?? null,
                        'programa' => $entry['Programa__c'] ?? null,
                        'programa2' => $entry['Programa2__c'] ?? null,
                        'piso' => $entry['Piso__c'] ?? null,
                        'precio_base' => (float) ($entry['Precio_Base__c'] ?? 0) ?: 0,
                        'precio_lista' => (float) ($entry['Precio_Lista__c'] ?? 0) ?: 0,
                        'porcentaje_maximo_unidad' => (float) ($entry['Porcentaje_maximo_de_unidad__c'] ?? 0) ?: 0,
                        'superficie_total_principal' => (float) ($entry['Superficie_Total_Producto_Principal__c'] ?? 0),
                        'superficie_interior' => (float) ($entry['Superficie_Interior__c'] ?? 0),
                        'superficie_util' => (float) ($entry['Superficie_Util__c'] ?? 0),
                        'superficie_terraza' => (float) ($entry['Superficie_Terraza__c'] ?? 0),
                        'proyecto_id' => $entry['Proyecto__c'] ?? null,
                    ];
                }, $entries);
            }
        });
    }

    /**
     * Invalidar caché de plantas
     */
    public function invalidatePlantsCache(): void
    {
        Cache::forget('salesforce:plants');
        // Limpiar también el caché de plantas por pricebook (usar tags sería ideal aquí)
    }

    /**
     * @return list<string>
     */
    private function getConfiguredPlantProductTypes(): array
    {
        $configuredTypes = SiteSetting::get('salesforce_sync_plant_types', ['DEPARTAMENTO']);

        if (! is_array($configuredTypes)) {
            return ['DEPARTAMENTO'];
        }

        $normalizedTypes = array_values(array_unique(array_filter(array_map(
            static fn(mixed $type): string => strtoupper(trim((string) $type)),
            $configuredTypes
        ), static fn(string $type): bool => $type !== '')));

        return $normalizedTypes === [] ? ['DEPARTAMENTO'] : $normalizedTypes;
    }

    /**
     * @param  list<string>  $productTypes
     * @param  list<string>  $projectSalesforceIds
     */
    private function buildPlantsCacheKey(array $productTypes, array $projectSalesforceIds): string
    {
        return 'salesforce:plants:' . md5(implode('|', $productTypes) . '::' . implode('|', $projectSalesforceIds));
    }

    /**
     * Obtener proyectos desde Proyecto__c disponibles
     * Estructura: Proyectos activos de tipo DEPARTAMENTO
     *
     * @return array Array de proyectos con estructura:
     *               [
     *               'id' => string,
     *               'name' => string,
     *               'descripcion' => string|null,
     *               'direccion' => string|null,
     *               'comuna' => string|null,
     *               'provincia' => string|null,
     *               'region' => string|null,
     *               'email' => string|null,
     *               'telefono' => string|null,
     *               'pagina_web' => string|null,
     *               'razon_social' => string|null,
     *               'rut' => string|null,
     *               'fecha_inicio_ventas' => string|null,
     *               'fecha_entrega' => string|null,
     *               'etapa' => string|null,
     *               'horario_atencion' => string|null,
     *               'valor_reserva_exigido_defecto_peso' => float|null,
     *               'valor_reserva_exigido_min_peso' => float|null,
     *               'descuento_defecto_cotizacion_web' => float|null,
     *               'descuento_maximo_unidad' => float|null,
     *               'entrega_inmediata' => bool
     *               ]
     */
    public function findProjects(?int $cacheTtl = null): array
    {
        // Asegurar que Forrest esté autenticado
        try {
            Forrest::authenticate();
        } catch (\Exception $e) {
            // Si falla, continuar - el query lo intentará
        }

        // SOQL para obtener proyectos desde Proyecto__c
        // Nota: Usamos Fecha_Recepcion_Municipal__c como proxy para fecha de entrega
        $soql = 'SELECT Id, Name, Descripci_n__c, Direccion__c, Comuna__c, Provincia__c, Region__c, '
            . 'Email__c, Telefono__c, Pagina_Web_Proyecto__c, Razon_Social__c, RUT__c, '
            . 'Fecha_Inicio_Ventas__c, Fecha_Recepcion_Municipal__c, Etapa__c, Horario_Atencion__c, '
            . 'Asesor_Responsable__c, Asesor_1__c, Asesor_2__c, '
            . 'Valor_Reserva_Exigido_Defecto_Peso__c, Valor_Reserva_Exigido_Min_Peso__c, '
            . 'Descuento_por_Defecto_Cotizaci_n_Web__c, Dscto_M_x_Prod_Principal_Porc__c, Entrega_Inmediata__c '
            . 'FROM Proyecto__c '
            . "WHERE IsDeleted = false AND Activo__c = true AND Tipo_Producto__c = 'DEPARTAMENTO' "
            . 'ORDER BY Name '
            . 'LIMIT 1000';

        $ttl = $cacheTtl ?? $this->defaultCacheTtl;

        return Cache::remember('salesforce:proyectos:v2', $ttl, function () use ($soql) {
            try {
                $result = Forrest::query($soql);
                $entries = $result['records'] ?? [];

                // Transformar estructura Salesforce a formato más amigable
                return array_map(function ($entry) {
                    return [
                        'id' => $entry['Id'] ?? null,
                        'name' => $entry['Name'] ?? null,
                        'descripcion' => $entry['Descripci_n__c'] ?? null,
                        'direccion' => $entry['Direccion__c'] ?? null,
                        'comuna' => $entry['Comuna__c'] ?? null,
                        'provincia' => $entry['Provincia__c'] ?? null,
                        'region' => $entry['Region__c'] ?? null,
                        'email' => $entry['Email__c'] ?? null,
                        'telefono' => $entry['Telefono__c'] ?? null,
                        'pagina_web' => $entry['Pagina_Web_Proyecto__c'] ?? null,
                        'razon_social' => $entry['Razon_Social__c'] ?? null,
                        'rut' => $entry['RUT__c'] ?? null,
                        'fecha_inicio_ventas' => $entry['Fecha_Inicio_Ventas__c'] ?? null,
                        'fecha_entrega' => $entry['Fecha_Recepcion_Municipal__c'] ?? null,
                        'etapa' => Proyecto::normalizeEtapa($entry['Etapa__c'] ?? null),
                        'horario_atencion' => $entry['Horario_Atencion__c'] ?? null,
                        'asesor_responsable_ids' => $this->normalizeSalesforceIdList([
                            $entry['Asesor_Responsable__c'] ?? null,
                            $entry['Asesor_1__c'] ?? null,
                            $entry['Asesor_2__c'] ?? null,
                        ]),
                        'valor_reserva_exigido_defecto_peso' => $entry['Valor_Reserva_Exigido_Defecto_Peso__c'] ? (float) $entry['Valor_Reserva_Exigido_Defecto_Peso__c'] : null,
                        'valor_reserva_exigido_min_peso' => $entry['Valor_Reserva_Exigido_Min_Peso__c'] ? (float) $entry['Valor_Reserva_Exigido_Min_Peso__c'] : null,
                        'descuento_defecto_cotizacion_web' => array_key_exists('Descuento_por_Defecto_Cotizaci_n_Web__c', $entry)
                            && $entry['Descuento_por_Defecto_Cotizaci_n_Web__c'] !== null
                            ? (float) $entry['Descuento_por_Defecto_Cotizaci_n_Web__c']
                            : null,
                        'descuento_maximo_unidad' => array_key_exists('Dscto_M_x_Prod_Principal_Porc__c', $entry)
                            && $entry['Dscto_M_x_Prod_Principal_Porc__c'] !== null
                            ? (float) $entry['Dscto_M_x_Prod_Principal_Porc__c']
                            : null,
                        'entrega_inmediata' => (bool) ($entry['Entrega_Inmediata__c'] ?? false),
                    ];
                }, $entries);
            } catch (\Throwable $e) {
                $this->authenticate();
                $result = Forrest::query($soql);
                $entries = $result['records'] ?? [];

                return array_map(function ($entry) {
                    return [
                        'id' => $entry['Id'] ?? null,
                        'name' => $entry['Name'] ?? null,
                        'descripcion' => $entry['Descripci_n__c'] ?? null,
                        'direccion' => $entry['Direccion__c'] ?? null,
                        'comuna' => $entry['Comuna__c'] ?? null,
                        'provincia' => $entry['Provincia__c'] ?? null,
                        'region' => $entry['Region__c'] ?? null,
                        'email' => $entry['Email__c'] ?? null,
                        'telefono' => $entry['Telefono__c'] ?? null,
                        'pagina_web' => $entry['Pagina_Web_Proyecto__c'] ?? null,
                        'razon_social' => $entry['Razon_Social__c'] ?? null,
                        'rut' => $entry['RUT__c'] ?? null,
                        'fecha_inicio_ventas' => $entry['Fecha_Inicio_Ventas__c'] ?? null,
                        'fecha_entrega' => $entry['Fecha_Recepcion_Municipal__c'] ?? null,
                        'etapa' => Proyecto::normalizeEtapa($entry['Etapa__c'] ?? null),
                        'horario_atencion' => $entry['Horario_Atencion__c'] ?? null,
                        'asesor_responsable_ids' => $this->normalizeSalesforceIdList([
                            $entry['Asesor_Responsable__c'] ?? null,
                            $entry['Asesor_1__c'] ?? null,
                            $entry['Asesor_2__c'] ?? null,
                        ]),
                        'valor_reserva_exigido_defecto_peso' => $entry['Valor_Reserva_Exigido_Defecto_Peso__c'] ? (float) $entry['Valor_Reserva_Exigido_Defecto_Peso__c'] : null,
                        'valor_reserva_exigido_min_peso' => $entry['Valor_Reserva_Exigido_Min_Peso__c'] ? (float) $entry['Valor_Reserva_Exigido_Min_Peso__c'] : null,
                        'descuento_defecto_cotizacion_web' => array_key_exists('Descuento_por_Defecto_Cotizaci_n_Web__c', $entry)
                            && $entry['Descuento_por_Defecto_Cotizaci_n_Web__c'] !== null
                            ? (float) $entry['Descuento_por_Defecto_Cotizaci_n_Web__c']
                            : null,
                        'descuento_maximo_unidad' => array_key_exists('Dscto_M_x_Prod_Principal_Porc__c', $entry)
                            && $entry['Dscto_M_x_Prod_Principal_Porc__c'] !== null
                            ? (float) $entry['Dscto_M_x_Prod_Principal_Porc__c']
                            : null,
                        'entrega_inmediata' => (bool) ($entry['Entrega_Inmediata__c'] ?? false),
                    ];
                }, $entries);
            }
        });
    }

    /**
     * Invalidar caché de proyectos
     */
    public function invalidateProjectsCache(): void
    {
        Cache::forget('salesforce:proyectos');
        Cache::forget('salesforce:proyectos:v2');
    }

    /**
     * Obtener usuarios de Salesforce por IDs.
     *
     * @param  list<string>  $salesforceUserIds
     * @return list<array{id: string|null, first_name: string|null, last_name: string|null, email: string|null, whatsapp_owner: string|null, avatar_url: string|null, is_active: bool}>
     */
    public function findSalesforceUsersByIds(array $salesforceUserIds, ?int $cacheTtl = null): array
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map(
            static fn(string $id): string => trim($id),
            $salesforceUserIds
        ), static fn(string $id): bool => $id !== '')));

        if ($normalizedIds === []) {
            return [];
        }

        $quotedIds = array_map(
            static fn(string $id): string => "'" . str_replace("'", "\\'", $id) . "'",
            $normalizedIds
        );

        $soql = 'SELECT Id, FirstName, LastName, Email, Whatsapp_owner__c, MediumPhotoUrl, IsActive '
            . 'FROM User '
            . 'WHERE Id IN (' . implode(',', $quotedIds) . ') '
            . 'LIMIT 2000';

        $records = $this->query($soql, $cacheTtl ?? $this->defaultCacheTtl);

        return array_map($this->mapSalesforceUserRecord(...), $records);
    }

    /**
     * Buscar un usuario (asesor) de Salesforce por email exacto.
     *
     * @return array{id: string|null, first_name: string|null, last_name: string|null, email: string|null, whatsapp_owner: string|null, avatar_url: string|null, is_active: bool}|null
     */
    public function findSalesforceUserByEmail(string $email, ?int $cacheTtl = null): ?array
    {
        $normalizedEmail = mb_strtolower(trim($email));

        if ($normalizedEmail === '') {
            return null;
        }

        $quotedEmail = str_replace("'", "\\'", $normalizedEmail);

        $soql = 'SELECT Id, FirstName, LastName, Email, Whatsapp_owner__c, MediumPhotoUrl, IsActive '
            . 'FROM User '
            . "WHERE Email = '{$quotedEmail}' "
            . 'LIMIT 1';

        $records = $this->query($soql, $cacheTtl ?? $this->defaultCacheTtl);

        if ($records === []) {
            return null;
        }

        return $this->mapSalesforceUserRecord($records[0]);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{id: string|null, first_name: string|null, last_name: string|null, email: string|null, whatsapp_owner: string|null, avatar_url: string|null, is_active: bool}
     */
    private function mapSalesforceUserRecord(array $entry): array
    {
        return [
            'id' => $entry['Id'] ?? null,
            'first_name' => $entry['FirstName'] ?? null,
            'last_name' => $entry['LastName'] ?? null,
            'email' => $entry['Email'] ?? null,
            'whatsapp_owner' => $entry['Whatsapp_owner__c'] ?? null,
            'avatar_url' => $entry['MediumPhotoUrl'] ?? null,
            'is_active' => (bool) ($entry['IsActive'] ?? true),
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeSalesforceIdList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map(
                static fn(mixed $item): string => trim((string) $item),
                $value
            ), static fn(string $item): bool => $item !== '')));
        }

        $asString = trim((string) $value);
        if ($asString === '') {
            return [];
        }

        if (str_contains($asString, ';')) {
            $parts = explode(';', $asString);

            return array_values(array_unique(array_filter(array_map(
                static fn(string $item): string => trim($item),
                $parts
            ), static fn(string $item): bool => $item !== '')));
        }

        if (str_contains($asString, ',')) {
            $parts = explode(',', $asString);

            return array_values(array_unique(array_filter(array_map(
                static fn(string $item): string => trim($item),
                $parts
            ), static fn(string $item): bool => $item !== '')));
        }

        return [$asString];
    }

    /**
     * Obtener documentos públicos de Salesforce para branding de cotizador (logo/portada).
     *
     * @param  list<string>  $documentNames
     * @return list<array{
     *   id: string|null,
     *   name: string|null,
     *   type: string|null,
     *   body_length: int,
     *   body_path: string|null,
     *   download_url: string|null,
     *   project_name: string|null,
     *   asset_kind: string|null,
     *   last_modified_at: string|null
     * }>
     */
    public function findPublicProjectDocuments(array $documentNames, ?int $cacheTtl = null): array
    {
        $names = array_values(array_unique(array_filter(array_map(
            static fn($name): string => trim((string) $name),
            $documentNames
        ), static fn(string $name): bool => $name !== '')));

        if ($names === []) {
            return [];
        }

        $quotedNames = array_map(
            static fn(string $name): string => "'" . str_replace("'", "\\'", $name) . "'",
            $names
        );

        $soql = 'SELECT Id, Name, Type, BodyLength, Body, LastModifiedDate FROM Document '
            . 'WHERE IsPublic = true AND Name IN (' . implode(',', $quotedNames) . ') '
            . 'ORDER BY Name';

        $ttl = $cacheTtl ?? $this->defaultCacheTtl;
        $records = $this->query($soql, $ttl);

        return $this->mapPublicProjectDocuments($records);
    }

    /**
     * Obtener todos los documentos públicos de cotizador (logo/portada) sin lista fija de nombres.
     *
     * @return list<array{
     *   id: string|null,
     *   name: string|null,
     *   type: string|null,
     *   body_length: int,
     *   body_path: string|null,
     *   download_url: string|null,
     *   project_name: string|null,
     *   asset_kind: string|null,
     *   last_modified_at: string|null
     * }>
     */
    public function findPublicCotizadorDocuments(?int $cacheTtl = null): array
    {
        $soql = 'SELECT Id, Name, Type, BodyLength, Body, LastModifiedDate FROM Document '
            . "WHERE IsPublic = true AND (Name LIKE '% - Cotizador Portada' OR Name LIKE '% - Cotizador Logo') "
            . 'ORDER BY Name';

        $ttl = $cacheTtl ?? $this->defaultCacheTtl;
        $records = $this->query($soql, $ttl);

        return $this->mapPublicProjectDocuments($records);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<array{
     *   id: string|null,
     *   name: string|null,
     *   type: string|null,
     *   body_length: int,
     *   body_path: string|null,
     *   download_url: string|null,
     *   project_name: string|null,
     *   asset_kind: string|null,
     *   last_modified_at: string|null
     * }>
     */
    private function mapPublicProjectDocuments(array $records): array
    {
        return array_map(function (array $record): array {
            $name = $record['Name'] ?? null;
            $bodyPath = $record['Body'] ?? null;
            $documentId = $record['Id'] ?? null;
            $lastModifiedAt = $record['LastModifiedDate'] ?? null;

            return [
                'id' => $documentId,
                'name' => $name,
                'type' => $record['Type'] ?? null,
                'body_length' => (int) ($record['BodyLength'] ?? 0),
                'body_path' => $bodyPath,
                'download_url' => $this->buildSalesforceDownloadUrl($bodyPath, $documentId, $lastModifiedAt),
                'project_name' => $this->extractProjectNameFromDocumentName($name),
                'asset_kind' => $this->extractAssetKindFromDocumentName($name),
                'last_modified_at' => $lastModifiedAt,
            ];
        }, $records);
    }

    /**
     * @return list<string>
     */
    public function getDefaultProjectDocumentNames(): array
    {
        return [
            'Edificio Indi - Cotizador Portada',
            'Edificio Indi - Cotizador Logo',
            'Edificio Capitanes - Cotizador Portada',
            'Edificio Capitanes - Cotizador Logo',
        ];
    }

    private function buildSalesforceDownloadUrl(?string $bodyPath, ?string $documentId = null, ?string $lastModifiedAt = null): ?string
    {
        $publicSiteUrl = $this->resolvePublicSiteUrl();
        $orgId = $this->resolveOrgId();

        if ($documentId !== null && trim($documentId) !== '' && $publicSiteUrl !== null && $orgId !== null) {
            $query = [
                'id' => $documentId,
                'oid' => $orgId,
            ];

            $lastMod = $this->toLastModMillis($lastModifiedAt);
            if ($lastMod !== null) {
                $query['lastMod'] = $lastMod;
            }

            return rtrim($publicSiteUrl, '/') . '/servlet/servlet.ImageServer?' . http_build_query($query);
        }

        if ($bodyPath === null || trim($bodyPath) === '') {
            return null;
        }

        $instanceUrl = (string) config('services.salesforce.instance_url', '');
        if ($instanceUrl === '') {
            return null;
        }

        return rtrim($instanceUrl, '/') . '/' . ltrim($bodyPath, '/');
    }

    private function resolvePublicSiteUrl(): ?string
    {
        $configuredSiteUrl = trim((string) config('services.salesforce.public_site_url', ''));
        if ($configuredSiteUrl !== '') {
            return rtrim($configuredSiteUrl, '/');
        }

        $instanceUrl = trim((string) config('services.salesforce.instance_url', ''));
        if ($instanceUrl === '') {
            return null;
        }

        $parts = parse_url($instanceUrl);
        $host = $parts['host'] ?? null;
        if (! is_string($host) || $host === '') {
            return null;
        }

        $siteHost = str_replace('salesforce.com', 'salesforce-sites.com', $host);
        if ($siteHost === '' || $siteHost === $host) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';

        return sprintf('%s://%s', $scheme, $siteHost);
    }

    private function resolveOrgId(): ?string
    {
        $configuredOrgId = trim((string) config('services.salesforce.org_id', ''));
        if ($configuredOrgId !== '') {
            return $configuredOrgId;
        }

        $cacheKey = 'salesforce:org_id:auto';
        $cachedOrgId = Cache::get($cacheKey);
        if (is_string($cachedOrgId) && trim($cachedOrgId) !== '') {
            return $cachedOrgId;
        }

        $resolvedOrgId = $this->resolveOrgIdFromIdentity() ?? $this->resolveOrgIdFromOrganizationQuery();
        if ($resolvedOrgId !== null) {
            Cache::put($cacheKey, $resolvedOrgId, now()->addDay());
        }

        return $resolvedOrgId;
    }

    private function resolveOrgIdFromIdentity(): ?string
    {
        try {
            $identity = Forrest::identity();

            $identityUrl = null;
            if (is_array($identity)) {
                $identityUrl = $identity['id'] ?? $identity['identity'] ?? null;
            } elseif (is_string($identity)) {
                $identityUrl = $identity;
            }

            if (! is_string($identityUrl) || $identityUrl === '') {
                return null;
            }

            if (preg_match('/\/id\/([a-zA-Z0-9]{15,18})\//', $identityUrl, $matches) === 1) {
                return $matches[1];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function resolveOrgIdFromOrganizationQuery(): ?string
    {
        try {
            $result = Forrest::query('SELECT Id FROM Organization LIMIT 1');
            $records = $result['records'] ?? [];
            $orgId = $records[0]['Id'] ?? null;

            return is_string($orgId) && trim($orgId) !== '' ? $orgId : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function toLastModMillis(?string $lastModifiedAt): ?string
    {
        if ($lastModifiedAt === null || trim($lastModifiedAt) === '') {
            return null;
        }

        try {
            return (string) Carbon::parse($lastModifiedAt)->getTimestampMs();
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractProjectNameFromDocumentName(?string $documentName): ?string
    {
        if ($documentName === null || trim($documentName) === '') {
            return null;
        }

        return preg_replace('/\s*-\s*Cotizador\s*(Logo|Portada)\s*$/i', '', $documentName) ?: $documentName;
    }

    private function extractAssetKindFromDocumentName(?string $documentName): ?string
    {
        if ($documentName === null) {
            return null;
        }

        if (stripos($documentName, 'Cotizador Logo') !== false) {
            return 'logo';
        }

        if (stripos($documentName, 'Cotizador Portada') !== false) {
            return 'portada';
        }

        return null;
    }
}
