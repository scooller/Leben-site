<?php

namespace App\Http\Resources\Api;

use App\Models\PageSection;
use App\Models\SiteSetting;
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
            'sections' => $this->sections->map(function (PageSection $section): array {
                $data = $section->data ?? [];
                $title = $section->title;
                $isActive = (bool) $section->is_active;

                if ($section->section_key === 'project_of_month') {
                    $siteConfig = SiteSetting::forFrontend();
                    if (! empty($siteConfig['project_of_month'])) {
                        $pom = $siteConfig['project_of_month'];
                        $title = ! empty($pom['title']) ? $pom['title'] : $title;
                        if (isset($pom['enabled'])) {
                            $isActive = (bool) $pom['enabled'];
                        }
                        $data = array_merge($data, [
                            'title_highlight' => $pom['title_highlight'] ?? ($data['title_highlight'] ?? 'Leben'),
                            'description' => $pom['description'] ?? ($data['description'] ?? ''),
                            'text' => $pom['text'] ?? ($data['text'] ?? ''),
                            'photo_url' => $pom['photo_url'] ?? ($data['photo_url'] ?? ''),
                            'video_url' => $pom['video_url'] ?? ($data['video_url'] ?? ''),
                            'button_text' => $pom['button_text'] ?? ($data['button_text'] ?? ''),
                            'button_link' => $pom['button_link'] ?? ($data['button_link'] ?? ''),
                            'project' => $pom['project'] ?? null,
                            'project_id' => $pom['project_id'] ?? null,
                        ]);
                    }
                }

                $content = $section->content ?: ($data['content'] ?? $data['description'] ?? $data['text'] ?? null);

                return [
                    'key' => $section->section_key,
                    'title' => $title,
                    'subtitle' => $section->subtitle,
                    'eyebrow' => $section->eyebrow,
                    'type' => $section->type,
                    'content' => $content,
                    'sort_order' => $section->sort_order,
                    'is_active' => $isActive,
                    'data' => $data,
                ];
            })->values()->all(),
        ];
    }
}
