<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\MenuResource;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MenuController extends Controller
{
    public function showByLocation(string $location): JsonResponse
    {
        $cacheKey = "menu_location_{$location}";

        $menu = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($location) {
            return Menu::query()
                ->where('location', $location)
                ->with(['items' => function ($q): void {
                    $q->where('is_active', true)
                        ->whereNull('parent_id')
                        ->orderBy('sort_order')
                        ->with(['children' => function ($cq): void {
                            $cq->where('is_active', true)->orderBy('sort_order');
                        }]);
                }])
                ->first();
        });

        if (! $menu) {
            return response()->json([
                'error' => 'Menú no encontrado',
                'code' => 'MENU_NOT_FOUND',
            ], 404);
        }

        return (new MenuResource($menu))
            ->response()
            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }
}
