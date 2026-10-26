<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TestimonialResource;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TestimonialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projectId = $request->filled('project_id') ? (int) $request->input('project_id') : null;
        $cacheKey = $projectId ? "testimonials_project_{$projectId}" : 'testimonials_all';

        $testimonials = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($projectId) {
            $query = Testimonial::query()
                ->published()
                ->ordered()
                ->with(['avatarMedia', 'project']);

            if ($projectId !== null) {
                $query->where('project_id', $projectId);
            }

            return $query->get();
        });

        return TestimonialResource::collection($testimonials)
            ->response()
            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }
}
