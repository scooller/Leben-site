<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProyectoController extends Controller
{
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
        'image_url',
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
        'is_active',
        'entrega_inmediata',
        'salesforce_logo_url',
        'salesforce_portada_url',
        'image_url',
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
            $query->where('etapa', $request->input('etapa'));
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
        $computedFields = array_intersect(['image_url'], $requestedFields);
        $databaseFields = array_diff($requestedFields, $computedFields);

        if (count($databaseFields) > 0) {
            $query->select($databaseFields);
        }

        $perPage = (int) $request->input('perPage', 15);
        $proyectos = $query->paginate(max(1, min($perPage, 100)));

        // Add computed fields to response if needed
        if (count($computedFields) > 0) {
            $proyectos->transform(function (Proyecto $proyecto) use ($computedFields): array {
                $data = $proyecto->toArray();
                foreach ($computedFields as $field) {
                    if ($field === 'image_url') {
                        $data['image_url'] = $proyecto->image_url;
                    }
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
        $hideSalesforceId = false;

        $requestedFields = $this->resolveRequestedFields(request());

        if ($includePlantas && ! in_array('salesforce_id', $requestedFields, true)) {
            $requestedFields[] = 'salesforce_id';
            $hideSalesforceId = true;
        }

        if (count($requestedFields) > 0) {
            $query->select($requestedFields);
        }

        if ($includePlantas) {
            $query->with('plantas');
        }

        $proyecto = $query->findOrFail($id);

        if ($hideSalesforceId) {
            $proyecto->makeHidden('salesforce_id');
        }

        return response()->json($proyecto);
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
