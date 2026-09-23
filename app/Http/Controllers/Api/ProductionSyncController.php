<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asesor;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionSyncController extends Controller
{
    public function export(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Acceso no autorizado para exportar sincronización.');
        return response()->json([
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'app_env' => config('app.env'),
            ],
            'site_settings' => SiteSetting::current()->syncPayload(),
            'advisors' => Asesor::query()
                ->orderBy('id', 'asc')
                ->get()
                ->map(static fn(Asesor $advisor): array => $advisor->syncPayload())
                ->values()
                ->all(),
            'projects' => Proyecto::query()
                ->orderBy('id', 'asc')
                ->get()
                ->map(static fn(Proyecto $project): array => $project->syncPayload())
                ->values()
                ->all(),
            'plants' => Plant::query()
                ->orderBy('id', 'asc')
                ->get()
                ->map(static fn(Plant $plant): array => $plant->syncPayload())
                ->values()
                ->all(),
        ]);
    }
}
