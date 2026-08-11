<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnrichesPlantPayload;
use App\Http\Controllers\Controller;
use App\Models\Asesor;
use App\Models\Plant;
use App\Models\Proyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProyectoController extends Controller
{
    use EnrichesPlantPayload;

    /**
     * @var array<string, string>
     */
    private array $fieldAliases = [
        'nombre' => 'name',
        'activo' => 'is_active',
    ];

    /**
     * @var list<string>
     */
    private array $defaultFields = [
        'id',
        'name',
        'direccion',
        'comuna',
        'pagina_web',
        'etapa',
        'entrega_inmediata',
        'telefono',
        'horario_atencion',
        'salesforce_logo_url',
        'salesforce_portada_url',
        'project_image_id',
        'descuento_defecto_cotizacion_web',
        'descuento_maximo_unidad',
        'precio_desde',
        'tipologias',
    ];

    /**
     * @var list<string>
     */
    private array $allowedFields = [
        'id',
        'salesforce_id',
        'name',
        'slug',
        'tipo',
        'descripcion',
        'direccion',
        'comuna',
        'provincia',
        'region',
        'email',
        'telefono',
        'pagina_web',
        'razon_social',
        'rut',
        'fecha_inicio_ventas',
        'fecha_entrega',
        'etapa',
        'horario_atencion',
        'descuento_defecto_cotizacion_web',
        'descuento_maximo_unidad',
        'is_active',
        'entrega_inmediata',
        'salesforce_logo_url',
        'salesforce_portada_url',
        'project_image_id',
        'precio_desde',
        'tipologias',
        'created_at',
        'updated_at',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Proyecto::query()->where('is_active', true);

        // Filtros opcionales
        if (filled($request->input('region'))) {
            $query->where('region', $request->input('region'));
        }

        if (filled($request->input('comuna'))) {
            $query->where('comuna', $request->input('comuna'));
        }

        if (filled($request->input('etapa'))) {
            $etapa = Proyecto::normalizeEtapa($request->input('etapa'));

            if ($etapa !== null) {
                $query->where('etapa', $etapa);
            }
        }

        if (filled($request->input('q'))) {
            $term = trim((string) $request->input('q'));

            $query->where(function ($subQuery) use ($term): void {
                $subQuery
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('comuna', 'like', "%{$term}%")
                    ->orWhere('region', 'like', "%{$term}%")
                    ->orWhere('direccion', 'like', "%{$term}%");
            });
        }

        $entregaInmediata = $this->normalizeBoolean($request->input('entrega_inmediata'));
        if ($entregaInmediata !== null) {
            $query->where('entrega_inmediata', $entregaInmediata);
        }

        $tipoValues = $this->normalizeInputValues($request->input('tipo'));
        if (count($tipoValues) > 0) {
            $query->where(function ($subQuery) use ($tipoValues): void {
                foreach ($tipoValues as $tipo) {
                    $subQuery
                        ->orWhereJsonContains('tipo', $tipo)
                        ->orWhere('tipo', $tipo);
                }
            });
        }

        $requestedFields = $this->resolveRequestedFields($request);
        $computedFields = array_intersect(['project_image_id', 'precio_desde', 'tipologias'], $requestedFields);
        $databaseFields = array_diff($requestedFields, $computedFields);

        $needsPlantSummary = count(array_intersect(['precio_desde', 'tipologias'], $computedFields)) > 0;
        $hideSalesforceId = false;
        if ($needsPlantSummary && ! in_array('salesforce_id', $databaseFields, true)) {
            $databaseFields[] = 'salesforce_id';
            $hideSalesforceId = true;
        }

        if (count($databaseFields) > 0) {
            $query->select($databaseFields);
        }

        $perPage = (int) $request->input('perPage', 15);
        $proyectos = $query->paginate(max(1, min($perPage, 100)));

        $discountFields = array_intersect(
            ['descuento_defecto_cotizacion_web', 'descuento_maximo_unidad'],
            $requestedFields,
        );

        if (count($computedFields) > 0 || count($discountFields) > 0 || $hideSalesforceId) {
            $plantSummaries = $needsPlantSummary
                ? $this->batchPlantSummaries($proyectos->getCollection()->pluck('salesforce_id')->filter()->values()->all())
                : [];

            $proyectos->transform(function (Proyecto $proyecto) use ($computedFields, $discountFields, $plantSummaries, $hideSalesforceId): array {
                $data = $proyecto->toArray();

                foreach ($computedFields as $field) {
                    if ($field === 'project_image_id') {
                        $data['project_image_id'] = $proyecto->project_image_id;
                    } elseif ($field === 'precio_desde') {
                        $data['precio_desde'] = $plantSummaries[$proyecto->salesforce_id]['precio_desde'] ?? null;
                    } elseif ($field === 'tipologias') {
                        $data['tipologias'] = $plantSummaries[$proyecto->salesforce_id]['tipologias'] ?? [];
                    }
                }

                foreach ($discountFields as $field) {
                    if (array_key_exists($field, $data) && $data[$field] === null) {
                        $data[$field] = 0;
                    }
                }

                if ($hideSalesforceId) {
                    unset($data['salesforce_id']);
                }

                return $data;
            });
        }

        return response()->json($proyectos);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $query = Proyecto::query();
        $includePlantas = $this->normalizeBoolean(request()->input('include_plantas')) === true;
        $includeAsesores = $this->normalizeBoolean(request()->input('include_asesores')) === true;
        $hideSalesforceId = false;
        $eventoSale = $this->resolveEventoSaleActive();

        $requestedFields = $this->resolveRequestedFields(request());
        $summaryFields = array_intersect(['precio_desde', 'tipologias'], $requestedFields);
        $selectableFields = array_diff($requestedFields, $summaryFields);

        if (($includePlantas || count($summaryFields) > 0) && ! in_array('salesforce_id', $selectableFields, true)) {
            $selectableFields[] = 'salesforce_id';
            $hideSalesforceId = true;
        }

        if (count($selectableFields) > 0) {
            $query->select($selectableFields);
        }

        if ($includePlantas || $includeAsesores) {
            $asesoresConstraint = function ($q): void {
                $q->where('asesores.is_active', true)
                    ->select(['asesores.id', 'asesores.is_active', 'asesores.first_name', 'asesores.last_name', 'asesores.email', 'asesores.whatsapp_owner', 'asesores.avatar_url', 'asesores.avatar_image_id'])
                    ->with('avatarImageMedia');
            };

            if ($includePlantas) {
                $query->with([
                    'asesores' => $asesoresConstraint,
                    'plantas' => function ($q) {
                        $q->with([
                            'asesor.avatarImageMedia',
                            'activeReservation',
                            'completedReservation',
                            'completedPayment',
                            'coverImageMedia',
                            'interiorImageMedia',
                        ]);
                    },
                ]);
            } else {
                $query->with(['asesores' => $asesoresConstraint]);
            }
        }

        $proyecto = $query->findOrFail($id);

        if ($hideSalesforceId) {
            $proyecto->makeHidden('salesforce_id');
        }

        $payload = $proyecto->toArray();

        foreach (['descuento_defecto_cotizacion_web', 'descuento_maximo_unidad'] as $discountField) {
            if (array_key_exists($discountField, $payload) && $payload[$discountField] === null) {
                $payload[$discountField] = 0;
            }
        }

        if ($hideSalesforceId) {
            unset($payload['salesforce_id']);
        }

        $includeAsesoresInPayload = $includePlantas || $includeAsesores;
        if ($includeAsesoresInPayload && isset($payload['asesores'])) {
            $payload['asesores'] = collect($proyecto->asesores)->map(function (Asesor $asesor): array {
                return [
                    'id' => $asesor->id,
                    'full_name' => $asesor->full_name,
                    'first_name' => $asesor->first_name,
                    'last_name' => $asesor->last_name,
                    'email' => $asesor->email,
                    'whatsapp_owner' => $asesor->whatsapp_owner,
                    'resolved_avatar_url' => $asesor->resolved_avatar_url,
                ];
            })->values()->toArray();
        }

        if ($includePlantas && isset($payload['plantas'])) {
            $defaultAdvisorAvatarUrl = $this->getDefaultAdvisorAvatarUrl();
            $payload['plantas'] = collect($proyecto->plantas)->map(function (Plant $plant) use ($proyecto, $eventoSale, $defaultAdvisorAvatarUrl): array {
                $plantPayload = $this->buildCompactPlantPayload($plant, $eventoSale, $defaultAdvisorAvatarUrl);

                // Override default advisor resolution: use proyecto's asesores collection
                // since plantas loaded via Proyecto don't have proyecto->asesores preloaded per plant
                if ($plant->asesor === null || ! (bool) $plant->asesor->is_active) {
                    $plantPayload['asesores'] = $proyecto->asesores
                        ->where('is_active', true)
                        ->values()
                        ->map(fn(Asesor $asesor): array => $this->asesorPayload($asesor, $defaultAdvisorAvatarUrl))
                        ->all();
                }

                return $plantPayload;
            })->values()->toArray();
        }

        if (count($summaryFields) > 0) {
            $plantSummaries = $this->batchPlantSummaries([$proyecto->salesforce_id]);
            $summary = $plantSummaries[$proyecto->salesforce_id] ?? ['precio_desde' => null, 'tipologias' => []];

            if (in_array('precio_desde', $summaryFields, true)) {
                $payload['precio_desde'] = $summary['precio_desde'];
            }
            if (in_array('tipologias', $summaryFields, true)) {
                $payload['tipologias'] = $summary['tipologias'];
            }
        }

        return response()->json($payload);
    }

    private function resolveEventoSaleActive(): ?bool
    {
        return $this->normalizeBoolean(request()->input('evento_sale'));
    }

    /**
     * Batch-compute precio_desde (min precio_lista) and tipologias (grouped by
     * programa/programa2/tipo_producto) from active plants for a set of projects.
     *
     * @param  list<string>  $salesforceIds
     * @return array<string, array{precio_desde: ?float, tipologias: list<array<string, mixed>>}>
     */
    private function batchPlantSummaries(array $salesforceIds): array
    {
        $summaries = array_fill_keys($salesforceIds, ['precio_desde' => null, 'tipologias' => []]);

        if (count($salesforceIds) === 0) {
            return $summaries;
        }

        $rows = Plant::query()
            ->where('is_active', true)
            ->whereIn('salesforce_proyecto_id', $salesforceIds)
            ->selectRaw('
                salesforce_proyecto_id,
                programa,
                programa2,
                tipo_producto,
                COUNT(*) as cantidad,
                MIN(NULLIF(precio_lista, 0)) as precio_min,
                MIN(NULLIF(superficie_util, 0)) as superficie_min,
                MAX(superficie_util) as superficie_max
            ')
            ->groupBy('salesforce_proyecto_id', 'programa', 'programa2', 'tipo_producto')
            ->get();

        foreach ($rows as $row) {
            $sid = $row->salesforce_proyecto_id;
            if (! isset($summaries[$sid])) {
                continue;
            }

            $precioMin = $row->precio_min !== null ? (float) $row->precio_min : null;
            if ($precioMin !== null && ($summaries[$sid]['precio_desde'] === null || $precioMin < $summaries[$sid]['precio_desde'])) {
                $summaries[$sid]['precio_desde'] = $precioMin;
            }

            $summaries[$sid]['tipologias'][] = [
                'programa' => $row->programa,
                'programa2' => $row->programa2,
                'tipo_producto' => $row->tipo_producto,
                'cantidad' => (int) $row->cantidad,
                'precio_desde' => $row->precio_min !== null ? (float) $row->precio_min : null,
                'superficie_util_min' => $row->superficie_min !== null ? (float) $row->superficie_min : null,
                'superficie_util_max' => (float) $row->superficie_max,
            ];
        }

        return $summaries;
    }

    /**
     * @return list<string>
     */
    private function resolveRequestedFields(Request $request): array
    {
        $requested = $this->normalizeInputValues($request->input('campos', $request->input('fields')));

        if (count($requested) === 0) {
            return $this->defaultFields;
        }

        $requested = array_values(array_map(function (string $field): string {
            $normalized = strtolower($field);

            return $this->fieldAliases[$normalized] ?? $field;
        }, $requested));

        $allowed = array_values(array_intersect($requested, $this->allowedFields));

        if (! in_array('id', $allowed, true)) {
            $allowed[] = 'id';
        }

        return $allowed;
    }

    /**
     * @return list<string>
     */
    private function normalizeInputValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn(mixed $item): string => trim((string) $item), $value), static fn(string $item): bool => $item !== ''));
        }

        if (is_string($value)) {
            if ($value === '') {
                return [];
            }

            if (str_contains($value, ',')) {
                $parts = explode(',', $value);

                return array_values(array_filter(array_map(static fn(string $item): string => trim($item), $parts), static fn(string $item): bool => $item !== ''));
            }

            return [trim($value)];
        }

        if ($value === null) {
            return [];
        }

        return [trim((string) $value)];
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalizedValue = strtolower(trim($value));

            if (in_array($normalizedValue, ['1', 'true', 'yes', 'si'], true)) {
                return true;
            }

            if (in_array($normalizedValue, ['0', 'false', 'no'], true)) {
                return false;
            }
        }

        return null;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
