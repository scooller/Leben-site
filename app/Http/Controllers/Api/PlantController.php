<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnrichesPlantPayload;
use App\Http\Controllers\Controller;
use App\Models\Asesor;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlantController extends Controller
{
    use EnrichesPlantPayload;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Plant::query()
            ->with(['proyecto.asesores.avatarImageMedia', 'asesor.avatarImageMedia', 'activeReservation', 'completedReservation', 'completedPayment', 'coverImageMedia', 'interiorImageMedia'])
            ->whereHas('proyecto', function ($projectQuery) {
                $projectQuery->where('is_active', true);
            }) // Solo plantas con proyecto activo asociado
            ->where('is_active', true); // Solo plantas activas

        $projectValues = $this->normalizeInputValues($request->input('salesforce_proyecto_id'));
        $projectIdValues = $this->normalizeInputValues($request->input('proyecto_id', $request->input('project_id')));
        $projectSlugValues = $this->normalizeInputValues($request->input('project_slug', $request->input('slug')));
        $comunaSlugValues = $this->normalizeInputValues($request->input('comuna_slug'));
        $catalogSlugValues = $this->normalizeInputValues($request->input('catalog_slug'));
        $dormValues = $this->normalizeInputValues($request->input('programa'));
        $banosValues = $this->normalizeInputValues($request->input('programa2'));
        $pisoValues = $this->normalizeInputValues($request->input('piso'));
        $orientacionValues = $this->normalizeInputValues($request->input('orientacion'));
        $tipoProductoValues = $this->normalizeInputValues($request->input('tipo_producto'));
        $tipoProductoSlugValues = $this->normalizeInputValues($request->input('tipo_producto_slug', $request->input('tipo_slug')));
        $comunaValues = $this->normalizeInputValues($request->input('comuna'));
        $provinciaValues = $this->normalizeInputValues($request->input('provincia'));
        $regionValues = $this->normalizeInputValues($request->input('region'));
        $entregaValues = $this->normalizeEtapaValues($request->input('entrega'));
        $eventoSale = $this->resolveEventoSaleActive($request);
        $discountSource = $this->resolveSalesforceDiscountSource();
        $available = $this->normalizeBoolean($request->input('disponible', $request->input('available')));

        // Filtros
        if (count($projectValues) > 0) {
            $query->whereIn('salesforce_proyecto_id', $projectValues);
        }

        if (count($projectIdValues) > 0) {
            $query->whereHas('proyecto', function ($projectQuery) use ($projectIdValues) {
                $projectQuery->whereIn('id', $projectIdValues);
            });
        }

        if (count($projectSlugValues) > 0) {
            $query->whereHas('proyecto', function ($projectQuery) use ($projectSlugValues) {
                $projectQuery->whereIn('slug', $projectSlugValues);
            });
        }

        if (count($comunaSlugValues) > 0 || count($catalogSlugValues) > 0) {
            $activeProjects = Proyecto::query()
                ->where('is_active', true)
                ->get(['slug', 'comuna']);

            $matchedProjectSlugs = [];
            $matchedComunas = [];

            foreach ($comunaSlugValues as $comunaSlugValue) {
                $normalizedComunaSlug = Str::slug($comunaSlugValue);

                foreach ($activeProjects as $project) {
                    if ($project->comuna && Str::slug($project->comuna) === $normalizedComunaSlug) {
                        $matchedComunas[] = $project->comuna;
                    }
                }
            }

            foreach ($catalogSlugValues as $catalogSlugValue) {
                $normalizedCatalogSlug = Str::slug($catalogSlugValue);

                $projectSlugMatchExists = $activeProjects->contains(
                    fn (Proyecto $project): bool => $project->slug === $normalizedCatalogSlug
                );

                if ($projectSlugMatchExists) {
                    $matchedProjectSlugs[] = $normalizedCatalogSlug;
                }

                foreach ($activeProjects as $project) {
                    if ($project->comuna && Str::slug($project->comuna) === $normalizedCatalogSlug) {
                        $matchedComunas[] = $project->comuna;
                    }
                }
            }

            if (count($matchedProjectSlugs) > 0) {
                $query->whereHas('proyecto', function ($projectQuery) use ($matchedProjectSlugs) {
                    $projectQuery->whereIn('slug', array_values(array_unique($matchedProjectSlugs)));
                });
            }

            if (count($matchedComunas) > 0) {
                $comunaValues = array_values(array_unique([...$comunaValues, ...$matchedComunas]));
            }
        }

        if ($available !== null) {
            if ($available) {
                $query
                    ->whereDoesntHave('activeReservation')
                    ->whereDoesntHave('completedReservation')
                    ->whereDoesntHave('completedPayment');
            } else {
                $query->where(function ($unavailableQuery) {
                    $unavailableQuery
                        ->whereHas('activeReservation')
                        ->orWhereHas('completedReservation')
                        ->orWhereHas('completedPayment');
                });
            }
        }

        if (count($dormValues) > 0 || count($banosValues) > 0) {
            $query->where(function ($subQuery) use ($dormValues, $banosValues) {
                $normalizedColumn = "REPLACE(programa, ' ', '')";

                if (count($dormValues) > 0) {
                    $subQuery->where(function ($dormQuery) use ($normalizedColumn, $dormValues) {
                        foreach ($dormValues as $dormValue) {
                            $dormText = strtoupper((string) $dormValue);
                            $dormNumber = preg_replace('/\D+/', '', $dormText);

                            if ($dormText === 'ST') {
                                $dormQuery->orWhereRaw($normalizedColumn.' like ?', ['ST%']);
                            } elseif ($dormNumber !== '') {
                                $dormQuery->orWhereRaw($normalizedColumn.' like ?', [$dormNumber.'D%']);
                            }
                        }
                    });
                }

                if (count($banosValues) > 0) {
                    $subQuery->where(function ($banosQuery) use ($normalizedColumn, $banosValues) {
                        foreach ($banosValues as $banosValue) {
                            $banosNumber = preg_replace('/\D+/', '', (string) $banosValue);

                            if ($banosNumber !== '') {
                                $banosQuery
                                    ->orWhereRaw($normalizedColumn.' like ?', ['%+'.$banosNumber.'B%'])
                                    ->orWhereRaw($normalizedColumn.' like ?', ['%+'.$banosNumber]);
                            }
                        }
                    });
                }
            });
        }

        if (count($pisoValues) > 0) {
            $query->whereIn('piso', $pisoValues);
        }

        if (count($orientacionValues) > 0) {
            $query->whereIn('orientacion', $orientacionValues);
        }

        if (count($tipoProductoSlugValues) > 0) {
            $availablePlantTypes = Plant::query()
                ->where('is_active', true)
                ->whereHas('proyecto', function ($projectQuery) {
                    $projectQuery->where('is_active', true);
                })
                ->pluck('tipo_producto')
                ->map(static fn (mixed $tipoProducto): string => trim((string) $tipoProducto))
                ->filter(static fn (string $tipoProducto): bool => $tipoProducto !== '')
                ->unique()
                ->values();

            $matchedPlantTypes = [];

            foreach ($tipoProductoSlugValues as $tipoProductoSlugValue) {
                $normalizedTypeSlug = Str::slug($tipoProductoSlugValue);

                foreach ($availablePlantTypes as $availablePlantType) {
                    if (Str::slug($availablePlantType) === $normalizedTypeSlug) {
                        $matchedPlantTypes[] = $availablePlantType;
                    }
                }
            }

            if (count($matchedPlantTypes) > 0) {
                $tipoProductoValues = array_values(array_unique([...$tipoProductoValues, ...$matchedPlantTypes]));
            }
        }

        if (count($tipoProductoValues) > 0) {
            $query->whereIn('tipo_producto', $tipoProductoValues);
        }

        if ($eventoSale === true) {
            $query->where('unidad_sale', true);
        }

        if (count($comunaValues) > 0 || count($provinciaValues) > 0 || count($regionValues) > 0 || count($entregaValues) > 0) {
            $query->whereHas('proyecto', function ($projectQuery) use ($comunaValues, $provinciaValues, $regionValues, $entregaValues) {
                if (count($comunaValues) > 0) {
                    $projectQuery->whereIn('comuna', $comunaValues);
                }

                if (count($provinciaValues) > 0) {
                    $projectQuery->whereIn('provincia', $provinciaValues);
                }

                if (count($regionValues) > 0) {
                    $projectQuery->whereIn('region', $regionValues);
                }

                if (count($entregaValues) > 0) {
                    $projectQuery->whereIn('etapa', $entregaValues);
                }
            });
        }

        if ($request->has('min_precio')) {
            $query->where('precio_base', '>=', $request->min_precio);
        }

        if ($request->has('max_precio')) {
            $query->where('precio_base', '<=', $request->max_precio);
        }

        // Obtener perPage del request o usar Site Settings (fallback 12)
        $defaultPerPage = (int) (SiteSetting::get('plants_per_page', 12) ?? 12);
        $perPage = (int) $request->input('perPage', $defaultPerPage);
        $perPage = max(1, min($perPage, 100));

        $projectDiscountExpression = '(SELECT p.descuento_maximo_unidad FROM proyectos p WHERE p.salesforce_id = plants.salesforce_proyecto_id LIMIT 1)';
        $legacyProjectDiscountExpression = '(SELECT p.descuento_defecto_cotizacion_web FROM proyectos p WHERE p.salesforce_id = plants.salesforce_proyecto_id LIMIT 1)';

        $orderByDiscountExpression = match ($discountSource) {
            'project' => "COALESCE({$projectDiscountExpression}, porcentaje_maximo_unidad, 0)",
            'plant' => "COALESCE(porcentaje_maximo_unidad, {$projectDiscountExpression}, 0)",
            default => $eventoSale === true
                ? 'COALESCE(porcentaje_maximo_unidad, 0)'
                : "COALESCE({$legacyProjectDiscountExpression}, 0)",
        };

        $query->orderByRaw(
            "COALESCE(CASE WHEN {$orderByDiscountExpression} > 0 AND precio_lista > 0 THEN CASE WHEN (precio_lista - ((precio_lista * {$orderByDiscountExpression}) / 100)) < 0 THEN 0 ELSE (precio_lista - ((precio_lista * {$orderByDiscountExpression}) / 100)) END ELSE precio_base END, 999999999999) ASC"
        )->orderBy('id');

        $plants = $query->paginate($perPage)->through(function (Plant $plant) use ($eventoSale, $discountSource): array {
            return $this->plantPayload($plant, $eventoSale, $discountSource);
        });

        return $this->noStoreJson($plants);
    }

    /**
     * @return list<string>
     */
    private function normalizeInputValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $value), static fn (string $item): bool => $item !== ''));
        }

        if (is_string($value)) {
            if ($value === '') {
                return [];
            }

            if (str_contains($value, ',')) {
                $parts = explode(',', $value);

                return array_values(array_filter(array_map(static fn (string $item): string => trim($item), $parts), static fn (string $item): bool => $item !== ''));
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
     * @return list<string>
     */
    private function normalizeEtapaValues(mixed $value): array
    {
        return collect($this->normalizeInputValues($value))
            ->map(static fn (string $item): ?string => Proyecto::normalizeEtapa($item))
            ->filter(static fn (?string $item): bool => $item !== null)
            ->values()
            ->all();
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $eventoSale = $this->resolveEventoSaleActive($request);

        $plant = Plant::query()
            ->with(['proyecto.asesores.avatarImageMedia', 'asesor.avatarImageMedia', 'activeReservation', 'completedReservation', 'completedPayment', 'coverImageMedia', 'interiorImageMedia'])
            ->whereHas('proyecto', function ($projectQuery) {
                $projectQuery->where('is_active', true);
            })
            ->findOrFail($id);

        return $this->noStoreJson($this->plantPayload($plant, $eventoSale, $this->resolveSalesforceDiscountSource()));
    }

    public function showByProjectSlugAndUnitName(Request $request, string $projectSlug, string $unitName): JsonResponse
    {
        $eventoSale = $this->resolveEventoSaleActive($request);
        $normalizedUnitName = trim($unitName);
        $normalizedUnitSlug = Str::of($normalizedUnitName)->lower()->replace(' ', '-')->value();

        $plant = Plant::query()
            ->with(['proyecto.asesores.avatarImageMedia', 'asesor.avatarImageMedia', 'activeReservation', 'completedReservation', 'completedPayment', 'coverImageMedia', 'interiorImageMedia'])
            ->where('is_active', true)
            ->where(function ($plantQuery) use ($normalizedUnitName, $normalizedUnitSlug) {
                $plantQuery
                    ->where('name', $normalizedUnitName)
                    ->orWhereRaw("LOWER(REPLACE(TRIM(name), ' ', '-')) = ?", [$normalizedUnitSlug]);
            })
            ->whereHas('proyecto', function ($projectQuery) use ($projectSlug) {
                $projectQuery
                    ->where('is_active', true)
                    ->where('slug', $projectSlug);
            })
            ->firstOrFail();

        return $this->noStoreJson($this->plantPayload($plant, $eventoSale, $this->resolveSalesforceDiscountSource()));
    }

    public function locationFilters(): JsonResponse
    {
        $projects = Proyecto::query()
            ->where('is_active', true)
            ->whereHas('plantas', function ($plantsQuery) {
                $plantsQuery->where('is_active', true);
            })
            ->get(['region', 'comuna', 'etapa']);

        $orientaciones = Plant::query()
            ->where('is_active', true)
            ->whereHas('proyecto', function ($projectQuery) {
                $projectQuery->where('is_active', true);
            })
            ->pluck('orientacion')
            ->map(static fn (mixed $orientacion): string => trim((string) $orientacion))
            ->filter(static fn (string $orientacion): bool => $orientacion !== '')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $tiposProducto = Plant::query()
            ->where('is_active', true)
            ->whereHas('proyecto', function ($projectQuery) {
                $projectQuery->where('is_active', true);
            })
            ->pluck('tipo_producto')
            ->map(static fn (mixed $tipoProducto): string => trim((string) $tipoProducto))
            ->filter(static fn (string $tipoProducto): bool => $tipoProducto !== '')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $pisos = Plant::query()
            ->where('is_active', true)
            ->whereHas('proyecto', function ($projectQuery) {
                $projectQuery->where('is_active', true);
            })
            ->pluck('piso')
            ->map(static fn (mixed $piso): string => trim((string) $piso))
            ->filter(static fn (string $piso): bool => $piso !== '')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $regions = $projects
            ->pluck('region')
            ->map(static fn (mixed $region): string => trim((string) $region))
            ->filter(static fn (string $region): bool => $region !== '')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $comunas = $projects
            ->pluck('comuna')
            ->map(static fn (mixed $comuna): string => trim((string) $comuna))
            ->filter(static fn (string $comuna): bool => $comuna !== '')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $comunasByRegion = $projects
            ->map(function (Proyecto $proyecto): array {
                return [
                    'region' => trim((string) $proyecto->region),
                    'comuna' => trim((string) $proyecto->comuna),
                ];
            })
            ->filter(static fn (array $entry): bool => $entry['region'] !== '' && $entry['comuna'] !== '')
            ->groupBy('region')
            ->map(function ($entries) {
                return collect($entries)
                    ->pluck('comuna')
                    ->unique()
                    ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();
            });

        $entregas = $projects
            ->pluck('etapa')
            ->map(static fn (mixed $etapa): string => trim((string) (Proyecto::etapaLabel($etapa) ?? '')))
            ->filter(static fn (string $etapa): bool => $etapa !== '')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return $this->noStoreJson([
            'regions' => $regions,
            'comunas' => $comunas,
            'comunas_by_region' => $comunasByRegion,
            'orientaciones' => $orientaciones,
            'tipos_producto' => $tiposProducto,
            'pisos' => $pisos,
            'entregas' => $entregas,
        ]);
    }

    private function noStoreJson(mixed $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Surrogate-Control' => 'no-store',
        ]);
    }

    private function plantPayload(Plant $plant, bool $eventoSale, ?string $discountSource): array
    {
        $payload = $this->buildPlantPayload($plant, $eventoSale, $discountSource);
        $payload['proyecto'] = $this->projectPayload($plant->proyecto, $plant, $eventoSale, $discountSource);

        return $payload;
    }

    private function resolveEventoSaleActive(Request $request): bool
    {
        $eventoSaleFromQuery = $this->normalizeBoolean($request->input('evento_sale'));

        if ($eventoSaleFromQuery !== null) {
            return $eventoSaleFromQuery;
        }

        return (bool) (SiteSetting::current()->evento_sale ?? false);
    }

    private function resolveSalesforceDiscountSource(): ?string
    {
        $settings = SiteSetting::current();
        $extraSettings = is_array($settings->extra_settings ?? null) ? $settings->extra_settings : [];
        $source = strtolower(trim((string) data_get($extraSettings, 'salesforce_discount_source', '')));

        return in_array($source, ['project', 'plant'], true) ? $source : null;
    }

    private function projectPayload(?Proyecto $proyecto, Plant $plant, bool $eventoSale, ?string $discountSource): ?array
    {
        if (! $proyecto) {
            return null;
        }

        $defaultAdvisorAvatarUrl = $this->getDefaultAdvisorAvatarUrl();
        $projectDiscountForApi = $discountSource === null
            ? $proyecto->descuento_defecto_cotizacion_web
            : $this->resolveApiDiscountPercentage($plant, $eventoSale, $discountSource);

        return [
            'id' => $proyecto->id,
            'name' => $proyecto->name,
            'slug' => $proyecto->slug,
            'tipo' => $proyecto->tipo,
            'direccion' => $proyecto->direccion,
            'comuna' => $proyecto->comuna,
            'provincia' => $proyecto->provincia,
            'region' => $proyecto->region,
            'pagina_web' => $proyecto->pagina_web,
            'etapa' => Proyecto::etapaLabel($proyecto->etapa),
            'horario_atencion' => $proyecto->horario_atencion,
            'entrega_inmediata' => $proyecto->entrega_inmediata,
            'is_active' => $proyecto->is_active,
            'image_url' => $proyecto->image_url,
            'salesforce_logo_url' => $proyecto->salesforce_logo_url,
            'valor_reserva_exigido_defecto_peso' => $proyecto->valor_reserva_exigido_defecto_peso,
            'valor_reserva_exigido_min_peso' => $proyecto->valor_reserva_exigido_min_peso,
            'descuento_defecto_cotizacion_web' => $projectDiscountForApi,
            'asesores' => $proyecto->asesores
                ->where('is_active', true)
                ->values()
                ->map(fn (Asesor $asesor): array => $this->asesorPayload($asesor, $defaultAdvisorAvatarUrl))
                ->all(),
        ];
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
