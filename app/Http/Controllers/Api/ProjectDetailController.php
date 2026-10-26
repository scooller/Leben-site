<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ProjectDetailResource;
use App\Models\Proyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ProjectDetailController extends Controller
{
    public function show(string $slugOrId): JsonResponse
    {
        $cacheKey = "project_content_{$slugOrId}";

        $proyecto = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($slugOrId) {
            $normalizedSlug = str_starts_with($slugOrId, 'edificio-')
                ? $slugOrId
                : 'edificio-'.$slugOrId;
            $rawSlug = str_replace('edificio-', '', $slugOrId);

            return Proyecto::query()
                ->where(function ($q) use ($slugOrId, $normalizedSlug, $rawSlug): void {
                    $q->where('slug', $slugOrId)
                        ->orWhere('slug', $normalizedSlug)
                        ->orWhere('slug', $rawSlug)
                        ->orWhere('id', is_numeric($slugOrId) ? (int) $slugOrId : -1);
                })
                ->where('is_active', true)
                ->with([
                    'detail.heroLogoMedia',
                    'detail.entornoMapMedia',
                    'detail.amenities.imageMedia',
                    'detail.pois.imageMedia',
                    'detail.galleries.imageMedia',
                    'testimonials' => fn ($tq) => $tq->where('is_published', true)->orderBy('sort_order'),
                ])
                ->first();
        });

        if (! $proyecto) {
            return response()->json([
                'error' => 'Proyecto no encontrado',
                'code' => 'PROJECT_NOT_FOUND',
            ], 404);
        }

        return (new ProjectDetailResource($proyecto))
            ->response()
            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }
}
