<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Broker;
use App\Models\BrokerAlliance;
use App\Models\BrokerEvent;
use App\Models\BrokerGallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BrokerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Broker::query()
            ->with(['user', 'avatarImageMedia', 'category.benefits'])
            ->where('is_active', true)
            ->orderBy('sort_order');

        if ($request->filled('category')) {
            $categorySlug = (string) $request->string('category');
            $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('slug', $categorySlug));
        }

        $brokers = $query->get()->map(function (Broker $broker): array {
            return [
                'id' => $broker->id,
                'name' => $broker->resolved_name,
                'email' => $broker->resolved_email,
                'phone' => $broker->resolved_phone,
                'avatar_url' => $broker->avatarImageMedia?->url,
                'category' => $broker->category ? [
                    'id' => $broker->category->id,
                    'name' => $broker->category->name,
                    'slug' => $broker->category->slug,
                    'headline' => $broker->category->headline,
                    'benefits' => $broker->category->benefits
                        ->where('is_active', true)
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn ($benefit): array => [
                            'id' => $benefit->id,
                            'section' => $benefit->section,
                            'title' => $benefit->title,
                            'description' => $benefit->description,
                            'status' => $benefit->pivot->status,
                        ])
                        ->all(),
                ] : null,
            ];
        })->values();

        return response()->json(['data' => $brokers]);
    }

    public function portal(Request $request): JsonResponse
    {
        $broker = Broker::query()
            ->with(['avatarImageMedia', 'category.benefits'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        $alliancesQuery = BrokerAlliance::query()
            ->with('imageMedia')
            ->where('is_active', true)
            ->orderBy('sort_order');

        if ($broker) {
            $alliancesQuery->where('broker_id', $broker->id);
        }

        $alliances = $alliancesQuery->get()->map(fn ($alliance): array => [
            'id' => $alliance->id,
            'name' => $alliance->name,
            'url' => $alliance->url,
            'image_url' => $alliance->imageMedia?->url,
        ])->values();

        $eventsQuery = BrokerEvent::query()
            ->with('imageMedia')
            ->where('is_published', true)
            ->orderBy('starts_at');

        if ($broker) {
            $eventsQuery->where('broker_id', $broker->id);
        }

        $events = $eventsQuery->get()->map(fn ($event): array => [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'starts_at' => optional($event->starts_at)?->toIso8601String(),
            'ends_at' => optional($event->ends_at)?->toIso8601String(),
            'location' => $event->location,
            'image_url' => $event->imageMedia?->url,
        ])->values();

        $galleriesQuery = BrokerGallery::query()
            ->with(['items' => fn ($q) => $q->with('imageMedia')->where('is_active', true)->orderBy('sort_order')])
            ->where('is_published', true)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('sort_order');

        if ($broker) {
            $galleriesQuery->where('broker_id', $broker->id);
        }

        $galleries = $galleriesQuery->get()->map(fn ($gallery): array => [
            'id' => $gallery->id,
            'title' => $gallery->title,
            'year' => $gallery->year,
            'month' => $gallery->month,
            'items_count' => $gallery->items->count(),
            'items' => $gallery->items->map(fn ($item): array => [
                'id' => $item->id,
                'caption' => $item->caption,
                'image_url' => $item->imageMedia?->url,
            ])->values(),
        ])->values();

        return response()->json([
            'data' => [
                'broker' => $broker ? [
                    'id' => $broker->id,
                    'name' => $broker->resolved_name,
                    'email' => $broker->resolved_email,
                    'phone' => $broker->resolved_phone,
                    'avatar_url' => $broker->avatarImageMedia?->url,
                ] : null,
                'alliances' => $alliances,
                'events' => $events,
                'galleries' => $galleries,
            ],
        ]);
    }

    public function alliances(Broker $broker): JsonResponse
    {
        $this->ensureBrokerIsActive($broker);

        $alliances = $broker->alliances()
            ->with('imageMedia')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($alliance): array => [
                'id' => $alliance->id,
                'name' => $alliance->name,
                'url' => $alliance->url,
                'image_url' => $alliance->imageMedia?->url,
            ])
            ->values();

        return response()->json(['data' => $alliances]);
    }

    public function events(Request $request, Broker $broker): JsonResponse
    {
        $this->ensureBrokerIsActive($broker);

        $query = $broker->events()
            ->with('imageMedia')
            ->where('is_published', true)
            ->orderBy('starts_at');

        if ($request->filled('from')) {
            $from = Carbon::parse((string) $request->input('from'))->startOfDay();
            $query->where('starts_at', '>=', $from);
        }

        if ($request->filled('to')) {
            $to = Carbon::parse((string) $request->input('to'))->endOfDay();
            $query->where('starts_at', '<=', $to);
        }

        $events = $query->get()->map(fn ($event): array => [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'starts_at' => optional($event->starts_at)?->toIso8601String(),
            'ends_at' => optional($event->ends_at)?->toIso8601String(),
            'location' => $event->location,
            'image_url' => $event->imageMedia?->url,
        ])->values();

        return response()->json(['data' => $events]);
    }

    public function galleries(Request $request, Broker $broker): JsonResponse
    {
        $this->ensureBrokerIsActive($broker);

        $query = $broker->galleries()
            ->where('is_published', true)
            ->withCount('items')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('sort_order');

        if ($request->filled('year')) {
            $query->where('year', (int) $request->input('year'));
        }

        if ($request->filled('month')) {
            $query->where('month', (int) $request->input('month'));
        }

        $galleries = $query->get()->map(fn ($gallery): array => [
            'id' => $gallery->id,
            'title' => $gallery->title,
            'year' => $gallery->year,
            'month' => $gallery->month,
            'items_count' => $gallery->items_count,
        ])->values();

        return response()->json(['data' => $galleries]);
    }

    public function showGallery(Broker $broker, BrokerGallery $gallery): JsonResponse
    {
        $this->ensureBrokerIsActive($broker);

        abort_unless($gallery->broker_id === $broker->id && $gallery->is_published, 404);

        $gallery->load(['items' => fn ($itemsQuery) => $itemsQuery
            ->with('imageMedia')
            ->where('is_active', true)
            ->orderBy('sort_order')]);

        return response()->json([
            'data' => [
                'id' => $gallery->id,
                'title' => $gallery->title,
                'year' => $gallery->year,
                'month' => $gallery->month,
                'items' => $gallery->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'caption' => $item->caption,
                    'image_url' => $item->imageMedia?->url,
                ])->values(),
            ],
        ]);
    }

    private function ensureBrokerIsActive(Broker $broker): void
    {
        abort_unless($broker->is_active, 404);
    }
}
