<?php

namespace App\Http\Resources\Api;

use App\Models\PageSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'badge' => $this->badge,
            'hero' => [
                'title' => $this->title,
                'subtitle' => $this->subtitle,
                'image_url' => $this->resolved_hero_image_url,
                'video_url' => $this->hero_video_url,
            ],
            'seo' => [
                'title' => $this->seo_title ?? $this->title,
                'description' => $this->seo_description,
                'og_image' => $this->resolved_seo_og_image_url,
            ],
            'sections' => $this->sections->map(fn (PageSection $section): array => [
                'key' => $section->section_key,
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'eyebrow' => $section->eyebrow,
                'type' => $section->type,
                'sort_order' => $section->sort_order,
                'is_active' => (bool) $section->is_active,
                'data' => $section->data ?? [],
            ])->values()->all(),
        ];
    }
}
