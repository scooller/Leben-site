<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProyectoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Proyecto::query();

        // Filtros opcionales
        if ($request->has('region')) {
            $query->where('region', $request->region);
        }

        if ($request->has('comuna')) {
            $query->where('comuna', $request->comuna);
        }

        $proyectos = $query->paginate(15);

        return response()->json($proyectos);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $proyecto = Proyecto::with('plantas')->findOrFail($id);

        return response()->json($proyecto);
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
