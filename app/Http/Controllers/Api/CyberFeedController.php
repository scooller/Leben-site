<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CyberFeed\CyberFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CyberFeedController extends Controller
{
    public function __construct(
        protected CyberFeedService $feedService
    ) {}

    /**
     * Endpoint público de solo lectura para el feed de Cyber AI (cyber.cl).
     */
    public function index(Request $request): JsonResponse|Response
    {
        try {
            $filters = [
                'project' => $request->input('project', $request->input('project_slug')),
                'commune' => $request->input('commune', $request->input('commune_slug')),
                'availability' => $request->input('availability'),
                'updated_since' => $request->input('updated_since'),
                'page' => max((int) $request->input('page', 1), 1),
                'per_page' => min(max((int) $request->input('per_page', 100), 1), 250),
            ];

            $cacheKey = 'cyber_feed_data_'.md5(serialize($filters));
            $cacheTtl = (int) (config('app.cyber_feed_cache_ttl') ?: env('CYBER_FEED_CACHE_TTL', 300));

            $feed = Cache::remember($cacheKey, $cacheTtl, function () use ($filters) {
                return $this->feedService->buildFeed($filters);
            });

            // Generar ETag basado en el payload del feed
            $payloadJson = json_encode($feed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $etag = '"'.md5($payloadJson).'"';

            // Soporte If-None-Match para 304 Not Modified
            $ifNoneMatch = $request->header('If-None-Match');
            if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
                return response('', 304, [
                    'ETag' => $etag,
                    'Cache-Control' => 'public, max-age=300, stale-while-revalidate=60',
                ]);
            }

            return response()->json($feed, 200, [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'public, max-age=300, stale-while-revalidate=60',
                'ETag' => $etag,
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[CyberFeedController] Error generando feed: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'error' => 'Internal server error while processing the feed.',
            ], 500, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        }
    }
}
