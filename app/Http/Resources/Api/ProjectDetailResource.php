<?php

namespace App\Http\Resources\Api;

use App\Models\ProjectAmenity;
use App\Models\ProjectDetail;
use App\Models\ProjectGallery;
use App\Models\ProjectPoi;
use App\Models\Proyecto;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Proyecto $proyecto */
        $proyecto = $this->resource;
        /** @var ProjectDetail|null $detail */
        $detail = $proyecto->detail;

        $themeColors = $detail?->theme_colors ?? [
            'accent' => '#d9bc70',
            'accent_dark' => '#c4a355',
            'accent_light' => '#e2ca8d',
            'accent_bg' => 'rgba(217, 188, 112, 0.12)',
            'gradient' => 'linear-gradient(135deg, #d9bc70 0%, #b89345 100%)',
            'btn_shadow' => '0 8px 24px rgba(217, 188, 112, 0.35)',
        ];

        $overviewImages = $detail?->overview_images ?? [];
        $equipmentSlides = $detail?->equipment_slides ?? [];
        $equipmentBrands = $detail?->equipment_brands ?? [];

        $amenities = $detail?->amenities ?? collect();
        $pois = $detail?->pois ?? collect();
        $galleries = $detail?->galleries ?? collect();

        $bottomGalleries = $galleries->where('category', 'bottom')->values();
        if ($bottomGalleries->isEmpty()) {
            $bottomGalleries = $galleries->values();
        }

        $testimonials = $proyecto->testimonials ?? collect();

        // Asesores vinculados al proyecto
        $asesores = $proyecto->asesores ?? collect();

        // Items de amenidades / espacios
        $espaciosItems = $detail?->espacios_items;
        if (empty($espaciosItems) && $amenities->isNotEmpty()) {
            $espaciosItems = $amenities->map(fn (ProjectAmenity $a): array => [
                'id' => $a->icon_key ?: 'hall',
                'navId' => $a->icon_key ?: 'hall',
                'label' => $a->name,
                'name' => $a->name,
                'icon_key' => $a->icon_key,
                'img' => $a->resolved_image_url,
                'link' => $a->link_url ?: $a->resolved_image_url,
                'alt' => $a->name,
            ])->values()->all();
        }

        // Puntos de interés (POIs) de mapa
        $mapPois = $detail?->map_pois;
        if (empty($mapPois) && $pois->isNotEmpty()) {
            $mapPois = $pois->map(fn (ProjectPoi $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'label' => strtoupper($p->name),
                'category' => $p->category,
                'distance' => $p->distance,
                'time' => $p->walking_time,
                'x' => (float) $p->coordinate_x,
                'y' => (float) $p->coordinate_y,
                'direction' => $p->pin_direction,
                'img' => $p->resolved_image_url,
            ])->values()->all();
        }

        return [
            'project_id' => $proyecto->id,
            'slug' => $proyecto->slug,
            'name' => $proyecto->name,
            'theme' => $themeColors,
            'hero' => [
                'eyebrow' => $detail?->hero_eyebrow,
                'title' => $detail?->hero_title ?? $proyecto->name,
                'subtitle' => $detail?->hero_subtitle ?? $proyecto->descripcion,
                'location' => $proyecto->comuna ?? $proyecto->region,
                'video_url' => $detail?->hero_video_url,
                'logo_url' => $detail?->resolved_hero_logo_url ?? $proyecto->salesforce_logo_url,
            ],
            'technical_info' => $detail?->technical_info ?? [
                ['id' => 'direccion', 'label' => 'Dirección', 'value' => $proyecto->direccion ?? ''],
                ['id' => 'comuna', 'label' => 'Comuna', 'value' => $proyecto->comuna ?? ''],
                ['id' => 'estado', 'label' => 'Estado del proyecto', 'value' => $proyecto->etapa ?? ''],
            ],
            'project_section' => [
                'eyebrow' => $detail?->project_eyebrow ?? 'Proyecto',
                'title' => $detail?->project_title ?? $proyecto->name,
                'description' => $detail?->project_description ?? $proyecto->descripcion,
                'highlight_offer' => $detail?->project_highlight_offer,
                'slides' => $detail?->project_slides ?? [],
            ],
            'project_slides' => $detail?->project_slides ?? [],
            'video_interstitials' => $detail?->video_interstitials ?? [],
            'equipment' => [
                'eyebrow' => $detail?->equipment_eyebrow ?? 'Equipamiento',
                'title' => $detail?->equipment_title ?? 'Equipamiento',
                'description' => $detail?->equipment_description ?? '',
                'bg_image_url' => $detail?->equipment_bg_image_url,
                'brands' => $equipmentBrands,
                'slides' => $equipmentSlides,
                'spaces_modal' => $detail?->equipment_spaces_modal ?? [],
            ],
            'overview' => [
                'eyebrow' => '1. Proyecto | Único en su clase',
                'title' => $detail?->overview_title ?? $proyecto->name,
                'description' => $detail?->overview_description ?? $proyecto->descripcion,
                'collage' => [
                    'left' => $overviewImages['left'] ?? $proyecto->imageUrl,
                    'right_top' => $overviewImages['right_top'] ?? null,
                    'right_bottom' => $overviewImages['right_bottom'] ?? null,
                ],
                'stats' => $detail?->overview_stats ?? [],
            ],
            'terminaciones' => [
                'eyebrow' => '2. Terminaciones | Diseñadas para durar',
                'title' => $detail?->terminaciones_title ?? 'Terminaciones',
                'description' => $detail?->terminaciones_description ?? '',
                'items' => $detail?->terminaciones_items ?? [],
            ],
            'virtual_tours' => [
                'matterport_url' => $detail?->virtual_tour_matterport_url,
                'panoramic_360_url' => $detail?->virtual_tour_360_url,
                'video_url' => $detail?->virtual_tour_video_url,
                'tours' => $detail?->virtual_tours_config ?? [],
            ],
            'espacios' => [
                'eyebrow' => $detail?->espacios_eyebrow ?? 'Espacios',
                'title' => $detail?->espacios_title ?? 'Espacios Comunes',
                'description' => $detail?->espacios_description ?? '',
                'bg_image_url' => $detail?->espacios_bg_image_url,
                'items' => $espaciosItems ?? [],
            ],
            'brochure_pdf_url' => $detail?->brochure_pdf_url,
            'map' => [
                'eyebrow' => $detail?->map_eyebrow ?? 'Ubicación',
                'title' => $detail?->map_title ?? 'Entorno Inigualable',
                'description' => $detail?->map_description ?? $detail?->entorno_description,
                'logo_url' => $detail?->map_logo_url,
                'btn_text' => $detail?->map_btn_text ?? 'Experiencias',
                'address' => $detail?->map_address ?? $proyecto->direccion,
                'image_url' => $detail?->resolved_entorno_map_image_url,
                'pin' => [
                    'x' => $detail?->map_pin_x ?? 33.3,
                    'y' => $detail?->map_pin_y ?? 72.4,
                    'logo_url' => $detail?->map_pin_logo_url,
                ],
                'pois' => $mapPois ?? [],
            ],
            'entorno' => [
                'description' => $detail?->entorno_description,
                'map_image_url' => $detail?->resolved_entorno_map_image_url,
            ],
            'interiorismo' => [
                'eyebrow' => $detail?->interiorismo_eyebrow ?? 'Interiorismo',
                'title' => $detail?->interiorismo_title ?? 'Interiorismo',
                'description' => $detail?->interiorismo_description ?? '',
                'bg_image_url' => $detail?->interiorismo_bg_image_url,
                'designers' => $detail?->designers ?? [],
            ],
            'designers' => $detail?->designers ?? [],
            'panoramas' => [
                'categories' => $detail?->panoramas_categories ?? ($detail?->panoramas ?? []),
            ],
            'contact' => [
                'eyebrow' => $detail?->contact_eyebrow ?? 'Contacto',
                'title' => $detail?->contact_title ?? 'Conversemos',
                'subtitle' => $detail?->contact_subtitle,
                'waze_url' => $detail?->contact_waze_url,
                'schedule' => $detail?->schedule ?? ($proyecto->horario_atencion ? [$proyecto->horario_atencion] : []),
                'agents' => $asesores->map(fn ($a): array => [
                    'id' => $a->id,
                    'name' => $a->full_name,
                    'phone' => $a->whatsapp_owner ?? $a->phone ?? '',
                    'email' => $a->email ?? '',
                    'avatar' => $a->avatar_url ?? $a->avatar_manual_url ?? '',
                ])->values()->all(),
            ],
            'amenities' => $amenities->map(fn (ProjectAmenity $a): array => [
                'id' => $a->id,
                'name' => $a->name,
                'icon_key' => $a->icon_key,
                'image_url' => $a->resolved_image_url,
                'link_url' => $a->link_url,
            ])->values()->all(),
            'pois' => $pois->map(fn (ProjectPoi $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category,
                'distance' => $p->distance,
                'walking_time' => $p->walking_time,
                'coordinate_x' => $p->coordinate_x,
                'coordinate_y' => $p->coordinate_y,
                'pin_direction' => $p->pin_direction,
                'image_url' => $p->resolved_image_url,
            ])->values()->all(),
            'bottom_gallery' => $bottomGalleries->map(fn (ProjectGallery $g): array => [
                'id' => $g->id,
                'url' => $g->resolved_image_url,
                'thumbnail_url' => $g->resolved_thumbnail_url,
                'title' => $g->title,
                'alt' => $g->alt_text ?? $g->title,
            ])->values()->all(),
            'schedule' => $detail?->schedule ?? ($proyecto->horario_atencion ? [$proyecto->horario_atencion] : []),
            'announcement' => $detail?->announcement ?? null,
            'testimonials' => $testimonials->map(fn (Testimonial $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'role' => $t->role,
                'avatar_url' => $t->resolved_avatar_url,
                'rating' => $t->rating,
                'review_text' => $t->review_text,
                'review_date_human' => $t->review_date_human,
            ])->values()->all(),
        ];
    }
}
