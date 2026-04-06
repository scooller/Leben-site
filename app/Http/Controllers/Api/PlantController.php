<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asesor;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use Awcodes\Curator\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Plant::query()
            ->with(['proyecto.asesores.avatarImageMedia', 'activeReservation', 'completedReservation', 'completedPayment', 'coverImageMedia', 'interiorImageMedia'])
            ->whereHas('proyecto', function ($projectQuery) {
                $projectQuery->where('is_active', true);
            }) // Solo plantas con proyecto activo asociado
            ->where('is_active', true); // Solo plantas activas

        $projectValues = $this->normalizeInputValues($request->input('salesforce_proyecto_id'));
        $projectIdValues = $this->normalizeInputValues($request->input('proyecto_id', $request->input('project_id')));
        $dormValues = $this->normalizeInputValues($request->input('programa'));
        $banosValues = $this->normalizeInputValues($request->input('programa2'));
        $pisoValues = $this->normalizeInputValues($request->input('piso'));
        $comunaValues = $this->normalizeInputValues($request->input('comuna'));
        $provinciaValues = $this->normalizeInputValues($request->input('provincia'));
        $regionValues = $this->normalizeInputValues($request->input('region'));
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

        if (count($comunaValues) > 0 || count($provinciaValues) > 0 || count($regionValues) > 0) {
            $query->whereHas('proyecto', function ($projectQuery) use ($comunaValues, $provinciaValues, $regionValues) {
                if (count($comunaValues) > 0) {
                    $projectQuery->whereIn('comuna', $comunaValues);
                }

                if (count($provinciaValues) > 0) {
                    $projectQuery->whereIn('provincia', $provinciaValues);
                }

                if (count($regionValues) > 0) {
                    $projectQuery->whereIn('region', $regionValues);
                }
            });
        }

        if ($request->has('min_precio')) {
            $query->where('precio_base', '>=', $request->min_precio);
        }

        if ($request->has('max_precio')) {
            $query->where('precio_base', '<=', $request->max_precio);
        }

        // Obtener perPage del request o usar 12 por defecto
        $perPage = $request->get('perPage', 12);
        $plants = $query->paginate($perPage)->through(function (Plant $plant): array {
            return $this->plantPayload($plant);
        });

        return response()->json($plants);
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
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $plant = Plant::query()
            ->with(['proyecto.asesores.avatarImageMedia', 'activeReservation', 'completedReservation', 'completedPayment', 'coverImageMedia', 'interiorImageMedia'])
            ->whereHas('proyecto', function ($projectQuery) {
                $projectQuery->where('is_active', true);
            })
            ->findOrFail($id);

        return response()->json($this->plantPayload($plant));
    }

    public function showByProjectSlugAndUnitName(string $projectSlug, string $unitName): JsonResponse
    {
        $normalizedUnitName = trim($unitName);
        $normalizedUnitSlug = Str::of($normalizedUnitName)->lower()->replace(' ', '-')->value();

        $plant = Plant::query()
            ->with(['proyecto.asesores.avatarImageMedia', 'activeReservation', 'completedReservation', 'completedPayment', 'coverImageMedia', 'interiorImageMedia'])
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

        return response()->json($this->plantPayload($plant));
    }

    public function locationFilters(): JsonResponse
    {
        $locations = Proyecto::query()
            ->where('is_active', true)
            ->whereHas('plantas', function ($plantsQuery) {
                $plantsQuery->where('is_active', true);
            })
            ->get(['region', 'comuna']);

        $regions = $locations
            ->pluck('region')
            ->map(static fn (mixed $region): string => trim((string) $region))
            ->filter(static fn (string $region): bool => $region !== '')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $comunas = $locations
            ->pluck('comuna')
            ->map(static fn (mixed $comuna): string => trim((string) $comuna))
            ->filter(static fn (string $comuna): bool => $comuna !== '')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $comunasByRegion = $locations
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

        return response()->json([
            'regions' => $regions,
            'comunas' => $comunas,
            'comunas_by_region' => $comunasByRegion,
        ]);
    }

    private function plantPayload(Plant $plant): array
    {
        $payload = $plant->toArray();

        unset($payload['cover_image_id'], $payload['interior_image_id']);

        $payload['cover_image_media'] = $this->mediaPayload($plant->coverImageMedia);
        $payload['interior_image_media'] = $this->mediaPayload($plant->interiorImageMedia);
        $payload['cover_image_url'] = $plant->coverImageMedia?->url;
        $payload['interior_image_url'] = $plant->interiorImageMedia?->url ?: $plant->salesforce_interior_image_url;
        $payload['salesforce_interior_image_url'] = $plant->salesforce_interior_image_url;
        $payload['proyecto'] = $this->projectPayload($plant->proyecto);
        $payload['projectLogoUrl'] = $this->resolveProjectLogoUrl($plant);
        $payload['proyectoImageUrl'] = $plant->proyecto?->image_url;
        $payload['imageUrl'] = $this->resolveImageUrl($plant);
        $payload['detailImageUrl'] = $this->resolveDetailImageUrl($plant);
        $payload['is_paid'] = $plant->completedReservation !== null || $plant->completedPayment !== null;
        $payload['is_available'] = $plant->activeReservation === null
            && $plant->completedReservation === null
            && $plant->completedPayment === null;

        return $payload;
    }

    private function resolveImageUrl(Plant $plant): string
    {
        // 1. Plant cover image
        if ($plant->coverImageMedia?->url) {
            return $plant->coverImageMedia->url;
        }

        // 2. Project image
        if ($plant->proyecto?->image_url) {
            return $plant->proyecto->image_url;
        }

        // 3. Site logo
        $siteSettings = SiteSetting::first();
        if ($siteSettings?->logoMedia?->url) {
            return $siteSettings->logoMedia->url;
        }

        // 4. Default SVG icon
        return $this->getDefaultImageUrl();
    }

    private function resolveDetailImageUrl(Plant $plant): string
    {
        // 1. Plant interior image (prioritize interior for detail view)
        if ($plant->interiorImageMedia?->url) {
            return $plant->interiorImageMedia->url;
        }

        // 2. Salesforce synced interior image URL
        if (filled($plant->salesforce_interior_image_url)) {
            return (string) $plant->salesforce_interior_image_url;
        }

        // 3. Fall back to all other images (same chain as cover)
        return $this->resolveImageUrl($plant);
    }

    private function resolveProjectLogoUrl(Plant $plant): ?string
    {
        if (filled($plant->proyecto?->salesforce_logo_url)) {
            return (string) $plant->proyecto->salesforce_logo_url;
        }

        return null;
    }

    private function getDefaultImageUrl(): string
    {
        return 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 300%22%3E%3Crect fill=%22%23e5e7eb%22 width=%22400%22 height=%22300%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%2248%22 fill=%22%239ca3af%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22 font-family=%22system-ui%22%3EPlanta%3C/text%3E%3C/svg%3E';
    }

    private function mediaPayload(?Media $media): ?array
    {
        if (! $media) {
            return null;
        }

        return [
            'type' => $media->type,
            'title' => $media->title,
            'url' => $media->url,
            'thumbnail_url' => $media->thumbnail_url,
            'medium_url' => $media->medium_url,
            'large_url' => $media->large_url,
        ];
    }

    private function projectPayload(?Proyecto $proyecto): ?array
    {
        if (! $proyecto) {
            return null;
        }

        $defaultAdvisorAvatarUrl = $this->getDefaultAdvisorAvatarUrl();

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
            'etapa' => $proyecto->etapa,
            'horario_atencion' => $proyecto->horario_atencion,
            'entrega_inmediata' => $proyecto->entrega_inmediata,
            'is_active' => $proyecto->is_active,
            'image_url' => $proyecto->image_url,
            'salesforce_logo_url' => $proyecto->salesforce_logo_url,
            'valor_reserva_exigido_defecto_peso' => $proyecto->valor_reserva_exigido_defecto_peso,
            'valor_reserva_exigido_min_peso' => $proyecto->valor_reserva_exigido_min_peso,
            'asesores' => $proyecto->asesores
                ->where('is_active', true)
                ->values()
                ->map(fn (Asesor $asesor): array => $this->asesorPayload($asesor, $defaultAdvisorAvatarUrl))
                ->all(),
        ];
    }

    private function asesorPayload(Asesor $asesor, string $defaultAvatarUrl): array
    {
        return [
            'id' => $asesor->id,
            'full_name' => $asesor->full_name,
            'first_name' => $asesor->first_name,
            'last_name' => $asesor->last_name,
            'email' => $asesor->email,
            'whatsapp_owner' => $asesor->whatsapp_owner,
            'avatar_url' => $asesor->avatarImageMedia?->url ?: $defaultAvatarUrl,
        ];
    }

    private function getDefaultAdvisorAvatarUrl(): string
    {
        $siteSettings = SiteSetting::query()->with('logoMedia', 'faviconMedia')->first();

        if (filled($siteSettings?->faviconMedia?->url)) {
            return (string) $siteSettings->faviconMedia->url;
        }

        if (filled($siteSettings?->logoMedia?->url)) {
            return (string) $siteSettings->logoMedia->url;
        }

        if (filled($siteSettings?->logo)) {
            return (string) $siteSettings->logo;
        }

        return 'data:image/svg+xml,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M320 312C253.7 312 200 258.3 200 192C200 125.7 253.7 72 320 72C386.3 72 440 125.7 440 192C440 258.3 386.3 312 320 312zM289.5 368L350.5 368C360.2 368 368 375.8 368 385.5C368 389.7 366.5 393.7 363.8 396.9L336.4 428.9L367.4 544L368 544L402.6 405.5C404.8 396.8 413.7 391.5 422.1 394.7C484 418.3 528 478.3 528 548.5C528 563.6 515.7 575.9 500.6 575.9L139.4 576C124.3 576 112 563.7 112 548.6C112 478.4 156 418.4 217.9 394.8C226.3 391.6 235.2 396.9 237.4 405.6L272 544.1L272.6 544.1L303.6 429L276.2 397C273.5 393.8 272 389.8 272 385.6C272 375.9 279.8 368.1 289.5 368.1z"/></svg>');
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
