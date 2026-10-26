<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PageResource;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PageController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $cacheKey = "page_{$slug}";

        $page = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($slug) {
            return Page::query()
                ->where('slug', $slug)
                ->where('is_published', true)
                ->with(['heroMedia', 'seoOgImageMedia', 'sections' => function ($q): void {
                    $q->where('is_active', true)->orderBy('sort_order');
                }])
                ->first();
        });

        if (! $page) {
            return response()->json([
                'error' => 'Página no encontrada',
                'code' => 'PAGE_NOT_FOUND',
            ], 404);
        }

        return (new PageResource($page))
            ->response()
            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }
}
