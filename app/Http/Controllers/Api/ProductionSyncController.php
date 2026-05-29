<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

class ProductionSyncController extends Controller
{
    public function export(): JsonResponse
    {
        return response()->json([
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'app_env' => config('app.env'),
            ],
            'site_settings' => SiteSetting::current()->syncPayload(),
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
