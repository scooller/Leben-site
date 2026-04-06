<?php

namespace App\Filament\Actions;

use App\Models\Asesor;
use App\Models\Proyecto;
use App\Services\Salesforce\SalesforceService;
use Exception;
use Filament\Actions\Action;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class SyncProjectsAction
{
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
                self::execute();
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
                    'is_active' => $proyectoData['is_active'] ?? true,
                    'dscto_m_x_prod_principal_porc' => $proyectoData['dscto_m_x_prod_principal_porc'],
                    'dscto_m_x_prod_principal_uf' => $proyectoData['dscto_m_x_prod_principal_uf'],
                    'dscto_m_x_bodega_porc' => $proyectoData['dscto_m_x_bodega_porc'],
                    'dscto_m_x_bodega_uf' => $proyectoData['dscto_m_x_bodega_uf'],
                    'dscto_m_x_estac_porc' => $proyectoData['dscto_m_x_estac_porc'],
                    'dscto_m_x_estac_uf' => $proyectoData['dscto_m_x_estac_uf'],
                    'dscto_max_otros_porc' => $proyectoData['dscto_max_otros_porc'],
                    'dscto_max_otros_prod_uf' => $proyectoData['dscto_max_otros_prod_uf'],
                    'dscto_maximo_aporte_leben' => $proyectoData['dscto_maximo_aporte_leben'],
                    'n_anos_1' => $proyectoData['n_anos_1'],
                    'n_anos_2' => $proyectoData['n_anos_2'],
                    'n_anos_3' => $proyectoData['n_anos_3'],
                    'n_anos_4' => $proyectoData['n_anos_4'],
                    'valor_reserva_exigido_defecto_peso' => $proyectoData['valor_reserva_exigido_defecto_peso'],
                    'valor_reserva_exigido_min_peso' => $proyectoData['valor_reserva_exigido_min_peso'],
                    'tasa' => $proyectoData['tasa'],
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

                if ($proyecto) {
                    $proyecto->update($data);
                    $updated++;
                } else {
                    $proyecto = Proyecto::create(array_merge(
                        ['salesforce_id' => $proyectoData['id']],
                        $data,
                        ['tipo' => $data['tipo'] ?? []]
                    ));
                    $synced++;
                }

                self::syncProyectoAsesores($proyecto, $proyectoData, $asesoresBySalesforceId);
            }

            return [
                'success' => true,
                'message' => "Sincronización completada. {$synced} nuevos proyectos, {$updated} actualizados",
                'count' => $synced + $updated,
                'created' => $synced,
                'updated' => $updated,
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
    }
}
