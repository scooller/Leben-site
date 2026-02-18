<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Plant::query()
            ->with('proyecto')
            ->whereHas('proyecto'); // Solo plantas con proyecto asociado

        // Filtros
        if ($request->has('salesforce_proyecto_id')) {
            $query->where('salesforce_proyecto_id', $request->salesforce_proyecto_id);
        }

        if ($request->filled('programa') || $request->filled('programa2')) {
            $dormValue = (string) $request->input('programa', '');
            $banosValue = (string) $request->input('programa2', '');
            $dormNumber = preg_replace('/\D+/', '', $dormValue);
            $banosNumber = preg_replace('/\D+/', '', $banosValue);

            $query->where(function ($subQuery) use ($dormValue, $dormNumber, $banosNumber) {
                $normalizedColumn = "REPLACE(programa, ' ', '')";

                if (strtoupper($dormValue) === 'ST') {
                    $subQuery->whereRaw($normalizedColumn.' like ?', ['ST%']);
                } elseif ($dormNumber !== '') {
                    $subQuery->whereRaw($normalizedColumn.' like ?', [$dormNumber.'D%']);
                }

                if ($banosNumber !== '') {
                    $subQuery->where(function ($banosQuery) use ($normalizedColumn, $banosNumber) {
                        $banosQuery
                            ->whereRaw($normalizedColumn.' like ?', ['%+'.$banosNumber.'B%'])
                            ->orWhereRaw($normalizedColumn.' like ?', ['%+'.$banosNumber]);
                    });
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
        $plants = $query->paginate($perPage);

        return response()->json($plants);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $plant = Plant::with('proyecto')->findOrFail($id);

        return response()->json($plant);
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
