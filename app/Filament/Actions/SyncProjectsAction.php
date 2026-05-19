<?php

namespace App\Filament\Actions;

use App\Models\Asesor;
use App\Models\ContactChannel;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Services\Salesforce\SalesforceService;
use App\Support\AsesorProyectoActivityLogger;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class SyncProjectsAction
{
    /**
     * @var array<string, string>
     */
    private const UPDATABLE_PROJECT_FIELD_OPTIONS = [
        'name' => 'Nombre',
        'descripcion' => 'Descripcion',
        'direccion' => 'Direccion',
        'comuna' => 'Comuna',
        'provincia' => 'Provincia',
        'region' => 'Region',
        'email' => 'Email',
        'telefono' => 'Telefono',
        'pagina_web' => 'Pagina web',
        'razon_social' => 'Razon social',
        'rut' => 'RUT',
        'fecha_inicio_ventas' => 'Fecha inicio ventas',
        'fecha_entrega' => 'Fecha entrega',
        'etapa' => 'Etapa',
        'horario_atencion' => 'Horario atencion',
        'valor_reserva_exigido_defecto_peso' => 'Valor reserva exigido defecto (peso)',
        'valor_reserva_exigido_min_peso' => 'Valor reserva exigido minimo (peso)',
        'descuento_defecto_cotizacion_web' => 'Descuento por defecto cotizacion web',
        'descuento_maximo_unidad' => 'Descuento maximo unidad',
        'entrega_inmediata' => 'Entrega inmediata',
        'tipo' => 'Tipo',
        'salesforce_logo_url' => 'Logo Salesforce',
        'salesforce_portada_url' => 'Portada Salesforce',
        'asesores' => 'Asesores',
    ];

    /**
     * @return array<string, string>
     */
    public static function getUpdatableFieldOptions(): array
    {
        return self::UPDATABLE_PROJECT_FIELD_OPTIONS;
    }

    /**
     * Crear acción para Filament
     */
    public static function make(): Action
    {
        return Action::make('sync_proyectos')
            ->label('Sincronizar Proyectos')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->action(function () {
                $result = self::execute();

                if (($result['success'] ?? false) === true) {
                    Notification::make()
                        ->title('Sincronizacion de proyectos completada')
                        ->body((string) ($result['message'] ?? 'Sincronizacion completada.'))
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Error al sincronizar proyectos')
                    ->body((string) ($result['message'] ?? 'Ocurrio un error durante la sincronizacion.'))
                    ->danger()
                    ->send();
            });
    }

    /**
     * Sincronizar proyectos desde Salesforce a la base de datos local
     */
    public static function execute(): array
    {
        try {
            $salesforceService = app(SalesforceService::class);
            $proyectos = $salesforceService->findProjects();
            $brandingSync = self::resolveSalesforceBranding($salesforceService);
            $asesoresBySalesforceId = self::syncAsesores($salesforceService, $proyectos);
            $excludedUpdateFields = self::resolveExcludedUpdateFields();

            if (empty($proyectos)) {
                return [
                    'success' => false,
                    'message' => 'No se encontraron proyectos en Salesforce',
                    'count' => 0,
                ];
            }

            $synced = 0;
            $updated = 0;

            foreach ($proyectos as $proyectoData) {
                $data = [
                    'name' => $proyectoData['name'],
                    'descripcion' => $proyectoData['descripcion'],
                    'direccion' => $proyectoData['direccion'],
                    'comuna' => $proyectoData['comuna'],
                    'provincia' => $proyectoData['provincia'],
                    'region' => $proyectoData['region'],
                    'email' => $proyectoData['email'],
                    'telefono' => $proyectoData['telefono'],
                    'pagina_web' => $proyectoData['pagina_web'],
                    'razon_social' => $proyectoData['razon_social'],
                    'rut' => $proyectoData['rut'],
                    'fecha_inicio_ventas' => $proyectoData['fecha_inicio_ventas'],
                    'fecha_entrega' => $proyectoData['fecha_entrega'],
                    'etapa' => $proyectoData['etapa'],
                    'horario_atencion' => $proyectoData['horario_atencion'],
                    'valor_reserva_exigido_defecto_peso' => $proyectoData['valor_reserva_exigido_defecto_peso'],
                    'valor_reserva_exigido_min_peso' => $proyectoData['valor_reserva_exigido_min_peso'],
                    'descuento_defecto_cotizacion_web' => $proyectoData['descuento_defecto_cotizacion_web'] ?? null,
                    'descuento_maximo_unidad' => $proyectoData['descuento_maximo_unidad'] ?? null,
                    'entrega_inmediata' => $proyectoData['entrega_inmediata'],
                ];

                if ($brandingSync['available'] === true) {
                    $branding = self::findBrandingForProject(
                        $brandingSync['by_project'],
                        $proyectoData['name'] ?? null
                    );

                    if (is_string($branding['salesforce_logo_url'] ?? null) && trim((string) $branding['salesforce_logo_url']) !== '') {
                        $data['salesforce_logo_url'] = $branding['salesforce_logo_url'];
                    }

                    if (is_string($branding['salesforce_portada_url'] ?? null) && trim((string) $branding['salesforce_portada_url']) !== '') {
                        $data['salesforce_portada_url'] = $branding['salesforce_portada_url'];
                    }
                }

                $normalizedTipo = self::normalizeTipo($proyectoData['tipo'] ?? null);
                if ($normalizedTipo !== null) {
                    $data['tipo'] = $normalizedTipo;
                }

                $proyecto = Proyecto::query()->where('salesforce_id', $proyectoData['id'])->first();
                $isExistingProject = $proyecto !== null;

                if ($proyecto) {
                    $proyecto->update(self::removeExcludedUpdateFields($data, $excludedUpdateFields));
                    $updated++;
                } else {
                    $proyecto = Proyecto::create(array_merge(
                        ['salesforce_id' => $proyectoData['id']],
                        $data,
                        ['is_active' => $proyectoData['is_active'] ?? true],
                        ['tipo' => $data['tipo'] ?? []]
                    ));
                    $synced++;
                }

                $shouldSkipAsesoresSync = $isExistingProject && in_array('asesores', $excludedUpdateFields, true);

                if (! $shouldSkipAsesoresSync) {
                    self::syncProyectoAsesores($proyecto, $proyectoData, $asesoresBySalesforceId);
                }
            }

            $channelsSync = self::syncContactChannelsFromProjects();

            return [
                'success' => true,
                'message' => "Sincronización completada. {$synced} nuevos proyectos, {$updated} actualizados, {$channelsSync['created']} canales creados, {$channelsSync['updated']} canales actualizados",
                'count' => $synced + $updated,
                'created' => $synced,
                'updated' => $updated,
                'channels_created' => $channelsSync['created'],
                'channels_updated' => $channelsSync['updated'],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al sincronizar: '.$e->getMessage(),
                'count' => 0,
            ];
        }
    }

    /**
     * @return list<string>
     */
    private static function resolveExcludedUpdateFields(): array
    {
        $extraSettings = SiteSetting::get('extra_settings', []);

        $configured = is_array($extraSettings)
            ? ($extraSettings['salesforce_sync_projects_excluded_fields'] ?? [])
            : [];

        if (! is_array($configured)) {
            return [];
        }

        $allowedKeys = array_keys(self::UPDATABLE_PROJECT_FIELD_OPTIONS);

        return array_values(array_intersect(
            $allowedKeys,
            array_map(static fn ($value): string => trim((string) $value), $configured)
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $excludedFields
     * @return array<string, mixed>
     */
    private static function removeExcludedUpdateFields(array $data, array $excludedFields): array
    {
        if ($excludedFields === []) {
            return $data;
        }

        return collect($data)
            ->except($excludedFields)
            ->all();
    }

    /**
     * @return array{available: bool, by_project: array<string, array{salesforce_logo_url: string|null, salesforce_portada_url: string|null}>}
     */
    private static function resolveSalesforceBranding(SalesforceService $salesforceService): array
    {
        try {
            $documents = $salesforceService->findPublicCotizadorDocuments();
        } catch (Throwable) {
            return [
                'available' => false,
                'by_project' => [],
            ];
        }

        $brandingByProject = [];

        foreach ($documents as $document) {
            $normalizedProjectName = self::normalizeProjectName($document['project_name'] ?? null);
            if ($normalizedProjectName === null) {
                continue;
            }

            if (! array_key_exists($normalizedProjectName, $brandingByProject)) {
                $brandingByProject[$normalizedProjectName] = [
                    'salesforce_logo_url' => null,
                    'salesforce_portada_url' => null,
                ];
            }

            $downloadUrl = $document['download_url'] ?? null;
            if (! is_string($downloadUrl) || trim($downloadUrl) === '') {
                continue;
            }

            if (($document['asset_kind'] ?? null) === 'logo') {
                $brandingByProject[$normalizedProjectName]['salesforce_logo_url'] = $downloadUrl;
            }

            if (($document['asset_kind'] ?? null) === 'portada') {
                $brandingByProject[$normalizedProjectName]['salesforce_portada_url'] = $downloadUrl;
            }
        }

        return [
            'available' => true,
            'by_project' => $brandingByProject,
        ];
    }

    /**
     * @param  array<string, array{salesforce_logo_url: string|null, salesforce_portada_url: string|null}>  $brandingByProject
     * @return array{salesforce_logo_url: string|null, salesforce_portada_url: string|null}
     */
    private static function findBrandingForProject(array $brandingByProject, ?string $projectName): array
    {
        $normalizedProjectName = self::normalizeProjectName($projectName);
        if ($normalizedProjectName === null) {
            return [
                'salesforce_logo_url' => null,
                'salesforce_portada_url' => null,
            ];
        }

        return $brandingByProject[$normalizedProjectName] ?? [
            'salesforce_logo_url' => null,
            'salesforce_portada_url' => null,
        ];
    }

    private static function normalizeProjectName(?string $projectName): ?string
    {
        if ($projectName === null) {
            return null;
        }

        $normalized = Str::of($projectName)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/^(edificio|condominio|proyecto)\s+/i', '', $normalized) ?? $normalized;

        return strtolower($normalized);
    }

    /**
     * Obtener total de proyectos
     */
    public static function getTotalProjects(): int
    {
        return Proyecto::count();
    }

    /**
     * Obtener fecha del último sync
     */
    public static function getLastSyncTime(): ?Carbon
    {
        return Proyecto::latest('updated_at')->first()?->updated_at;
    }

    /**
     * Normalizar tipos permitidos para el multiselect.
     */
    private static function normalizeTipo(mixed $tipo): ?array
    {
        if ($tipo === null) {
            return null;
        }

        $allowed = ['best', 'broker', 'home', 'icon', 'invest'];

        $values = is_array($tipo)
            ? $tipo
            : explode(',', (string) $tipo);

        $normalized = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            $values
        ))));

        return array_values(array_filter($normalized, static fn (string $value): bool => in_array($value, $allowed, true)));
    }

    /**
     * @param  list<array<string, mixed>>  $proyectos
     * @return array<string, int>
     */
    private static function syncAsesores(SalesforceService $salesforceService, array $proyectos): array
    {
        $salesforceUserIds = collect($proyectos)
            ->pluck('asesor_responsable_ids')
            ->filter()
            ->flatten()
            ->map(static fn ($value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();

        if ($salesforceUserIds === []) {
            return [];
        }

        $salesforceUsers = $salesforceService->findSalesforceUsersByIds($salesforceUserIds);
        if ($salesforceUsers === []) {
            return [];
        }

        foreach ($salesforceUsers as $salesforceUser) {
            $salesforceId = trim((string) ($salesforceUser['id'] ?? ''));
            if ($salesforceId === '') {
                continue;
            }

            Asesor::query()->updateOrCreate(
                ['salesforce_id' => $salesforceId],
                [
                    'first_name' => $salesforceUser['first_name'] ?? null,
                    'last_name' => $salesforceUser['last_name'] ?? null,
                    'email' => $salesforceUser['email'] ?? null,
                    'whatsapp_owner' => $salesforceUser['whatsapp_owner'] ?? null,
                    'avatar_url' => $salesforceUser['avatar_url'] ?? null,
                    'is_active' => (bool) ($salesforceUser['is_active'] ?? true),
                ]
            );
        }

        return Asesor::query()
            ->whereIn('salesforce_id', $salesforceUserIds)
            ->pluck('id', 'salesforce_id')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $proyectoData
     * @param  array<string, int>  $asesoresBySalesforceId
     */
    private static function syncProyectoAsesores(Proyecto $proyecto, array $proyectoData, array $asesoresBySalesforceId): void
    {
        $currentAsesorIds = $proyecto->asesores()
            ->pluck('asesores.id')
            ->map(static fn ($id): int => (int) $id)
            ->values();

        $salesforceAsesorIds = collect($proyectoData['asesor_responsable_ids'] ?? [])
            ->map(static fn ($value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique();

        if ($salesforceAsesorIds->isEmpty()) {
            return;
        }

        $localSalesforceAsesorIds = $salesforceAsesorIds
            ->map(static fn (string $salesforceId): ?int => $asesoresBySalesforceId[$salesforceId] ?? null)
            ->filter()
            ->values();

        if ($localSalesforceAsesorIds->isEmpty()) {
            return;
        }

        $manualAsesores = $proyecto->asesores()
            ->whereNull('asesores.salesforce_id')
            ->pluck('asesores.id');

        /** @var Collection<int, int> $finalAsesorIds */
        $finalAsesorIds = $manualAsesores
            ->merge($localSalesforceAsesorIds)
            ->unique()
            ->values();

        $proyecto->asesores()->sync($finalAsesorIds->all());

        $attachedAsesorIds = $finalAsesorIds
            ->diff($currentAsesorIds)
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        $detachedAsesorIds = $currentAsesorIds
            ->diff($finalAsesorIds)
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        AsesorProyectoActivityLogger::logSynced($proyecto, $attachedAsesorIds, $detachedAsesorIds);
    }

    /**
     * @return array{created:int, updated:int}
     */
    private static function syncContactChannelsFromProjects(): array
    {
        $created = 0;
        $updated = 0;

        Proyecto::query()
            ->select(['slug', 'name', 'is_active', 'pagina_web'])
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderBy('id')
            ->chunk(200, function (Collection $projects) use (&$created, &$updated): void {
                foreach ($projects as $project) {
                    $slug = trim((string) $project->slug);

                    if ($slug === '' || $slug === 'default') {
                        continue;
                    }

                    $channel = ContactChannel::query()->firstOrNew(['slug' => $slug]);
                    $isNew = ! $channel->exists;

                    if ($isNew) {
                        $channel->slug_badge_color = 'gray';
                    }

                    $channel->name = filled($project->name) ? (string) $project->name : $slug;
                    $channel->is_active = (bool) ($project->is_active ?? true);
                    $channel->is_default = false;

                    $domainPattern = self::extractDomainPatternFromWebsite($project->pagina_web);
                    $currentPatterns = collect((array) ($channel->domain_patterns ?? []))
                        ->map(static fn ($pattern): string => strtolower(trim((string) $pattern)))
                        ->filter(static fn (string $pattern): bool => $pattern !== '')
                        ->values();

                    if ($domainPattern !== null && ! $currentPatterns->contains($domainPattern)) {
                        $currentPatterns->push($domainPattern);
                    }

                    if ($currentPatterns->isNotEmpty()) {
                        $channel->domain_patterns = $currentPatterns->unique()->values()->all();
                    }

                    $channel->save();

                    if ($isNew) {
                        $created++;
                    } else {
                        $updated++;
                    }
                }
            });

        return ['created' => $created, 'updated' => $updated];
    }

    private static function extractDomainPatternFromWebsite(mixed $website): ?string
    {
        if (! is_string($website)) {
            return null;
        }

        $value = trim($website);
        if ($value === '') {
            return null;
        }

        if (! str_contains($value, '://')) {
            $value = 'https://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (! is_string($host)) {
            return null;
        }

        $host = strtolower(trim($host));
        if ($host === '' || \filter_var($host, \FILTER_VALIDATE_DOMAIN, \FILTER_FLAG_HOSTNAME) === false) {
            return null;
        }

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host !== '' ? $host : null;
    }
}
