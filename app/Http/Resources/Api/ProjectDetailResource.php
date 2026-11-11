<?php

namespace App\Http\Resources\Api;

use App\Models\ProjectAmenity;
use App\Models\ProjectDetail;
use App\Models\ProjectGallery;
use App\Models\ProjectPoi;
use App\Models\Proyecto;
use App\Models\Testimonial;
use Awcodes\Curator\Models\Media;
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
        $equipmentSlides = collect($detail?->equipment_slides ?? [])->map(function ($slide): array {
            $slide = (array) $slide;
            if (empty($slide['img']) && ! empty($slide['media_id'])) {
                $slide['img'] = ProjectDetail::resolveMediaUrl($slide['media_id']);
            }

            return $slide;
        })->values()->all();

        $equipmentBrands = collect($detail?->equipment_brands ?? [])->map(function ($brand): array {
            $brand = (array) $brand;
            if (empty($brand['src']) && ! empty($brand['media_id'])) {
                $brand['src'] = ProjectDetail::resolveMediaUrl($brand['media_id']);
            }

            return $brand;
        })->values()->all();

        $equipmentSpacesModal = collect($detail?->equipment_spaces_modal ?? [])->map(function ($space): array {
            $space = (array) $space;
            if (! empty($space['images']) && is_array($space['images'])) {
                $space['images'] = collect($space['images'])->map(function ($image): array {
                    $image = (array) $image;
                    if (empty($image['img']) && ! empty($image['media_id'])) {
                        $image['img'] = ProjectDetail::resolveMediaUrl($image['media_id']);
                    }

                    return $image;
                })->values()->all();
            }

            return $space;
        })->values()->all();

        $projectSlides = collect($detail?->project_slides ?? [])->map(function ($slide): array {
            $slide = (array) $slide;
            if (empty($slide['img']) && ! empty($slide['media_id'])) {
                $slide['img'] = ProjectDetail::resolveMediaUrl($slide['media_id']);
            }

            return $slide;
        })->values()->all();

        $designers = collect($detail?->designers ?? [])->map(function ($designer): array {
            $designer = (array) $designer;
            if (empty($designer['image']) && ! empty($designer['media_id'])) {
                $designer['image'] = ProjectDetail::resolveMediaUrl($designer['media_id']);
            }

            return $designer;
        })->values()->all();

        $panoramasCategories = collect($detail?->panoramas_categories ?? ($detail?->panoramas ?? []))->map(function ($cat): array {
            $cat = (array) $cat;
            if (! empty($cat['slides']) && is_array($cat['slides'])) {
                $cat['slides'] = collect($cat['slides'])->map(function ($slide): array {
                    $slide = (array) $slide;
                    if (empty($slide['image']) && ! empty($slide['media_id'])) {
                        $slide['image'] = ProjectDetail::resolveMediaUrl($slide['media_id']);
                    }

                    return $slide;
                })->values()->all();
            }

            return $cat;
        })->values()->all();

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
        if (! empty($espaciosItems) && is_array($espaciosItems)) {
            $espaciosItems = collect($espaciosItems)->map(function ($item): array {
                $item = (array) $item;
                if (empty($item['img']) && ! empty($item['media_id'])) {
                    $item['img'] = ProjectDetail::resolveMediaUrl($item['media_id']);
                }

                return $item;
            })->values()->all();
        } elseif (empty($espaciosItems) && $amenities->isNotEmpty()) {
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
        if (! empty($mapPois) && is_array($mapPois)) {
            $mapPois = collect($mapPois)->map(function ($poi): array {
                $poi = (array) $poi;
                if (empty($poi['img']) && ! empty($poi['media_id'])) {
                    $poi['img'] = ProjectDetail::resolveMediaUrl($poi['media_id']);
                }

                return $poi;
            })->values()->all();
        } elseif (empty($mapPois) && $pois->isNotEmpty()) {
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

        $rawTours = $detail?->virtual_tours_config ?? [];
        $normalizedTours = collect($rawTours)->map(function (array $tour): array {
            $interiores = collect($tour['interiores'] ?? $tour['images'] ?? [])->map(function ($interior): array {
                $interior = (array) $interior;
                $src = $interior['src'] ?? null;
                $mediaId = $interior['media_id'] ?? $interior['src_media_id'] ?? null;
                if (empty($src) && ! empty($mediaId)) {
                    $src = Media::find($mediaId)?->url;
                }

                return [
                    'title' => $interior['title'] ?? '',
                    'src' => $src,
                    'media_id' => $mediaId,
                ];
            })->values()->all();

            $src = $tour['src'] ?? null;
            $tourMediaId = $tour['media_id'] ?? $tour['src_media_id'] ?? null;
            if (empty($src) && ! empty($tourMediaId)) {
                $src = Media::find($tourMediaId)?->url;
            }

            return [
                'label' => $tour['label'] ?? '',
                'title' => $tour['title'] ?? '',
                'src' => $src,
                'media_id' => $tourMediaId,
                'allow' => $tour['allow'] ?? 'fullscreen; autoplay',
                'interiores' => $interiores,
                'images' => $interiores,
            ];
        })->values()->all();

        $videoInterstitials = collect($detail?->video_interstitials ?? [])->map(function ($item): array {
            $item = (array) $item;
            if (empty($item['video_url']) && ! empty($item['video_media_id'])) {
                $item['video_url'] = Media::find($item['video_media_id'])?->url;
            }

            return $item;
        })->all();

        if (! empty($detail?->espacios_video)) {
            $espaciosVideo = (array) $detail->espacios_video;
            if (empty($espaciosVideo['video_url']) && ! empty($espaciosVideo['video_media_id'])) {
                $espaciosVideo['video_url'] = Media::find($espaciosVideo['video_media_id'])?->url;
            }
            $videoInterstitials[2] = array_merge($videoInterstitials[2] ?? [], $espaciosVideo);
        }

        return [
            'project_id' => $proyecto->id,
            'slug' => $proyecto->slug,
            'name' => $proyecto->name,
            'cotiza_url' => $proyecto->cotiza_url,
            'custom_css' => $detail?->custom_css,
            'custom_js' => $detail?->custom_js,
            'theme' => $themeColors,
            'hero' => [
                'eyebrow' => $detail?->hero_eyebrow,
                'title' => $detail?->hero_title ?? $proyecto->name,
                'subtitle' => $detail?->hero_subtitle ?? $proyecto->descripcion,
                'location' => $proyecto->comuna ?? $proyecto->region,
                'video_url' => $detail?->resolved_hero_video_url ?? $detail?->hero_video_url,
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
                'bg_image_url' => $detail?->resolved_project_bg_image_url ?? $detail?->project_bg_image_url,
                'slides' => $projectSlides,
                'carousel_spacing' => $detail?->carouselSpacing('project'),
            ],
            'project_slides' => $projectSlides,
            'video_interstitials' => $videoInterstitials,
            'equipment' => [
                'eyebrow' => $detail?->equipment_eyebrow ?? 'Equipamiento',
                'title' => $detail?->equipment_title ?? 'Equipamiento',
                'description' => $detail?->equipment_description ?? '',
                'bg_image_url' => $detail?->resolved_equipment_bg_image_url,
                'brands' => $equipmentBrands,
                'slides' => $equipmentSlides,
                'spaces_modal' => $equipmentSpacesModal,
                'carousel_spacing' => $detail?->carouselSpacing('equipment'),
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
                'eyebrow' => $detail?->virtual_tours_eyebrow ?? 'Recorridos Virtuales',
                'title' => $detail?->virtual_tours_title ?? ('Descubre '.($proyecto->name ?? 'el Proyecto')),
                'matterport_url' => $detail?->virtual_tour_matterport_url,
                'panoramic_360_url' => $detail?->resolved_virtual_tour_360_url ?? $detail?->virtual_tour_360_url,
                'video_url' => $detail?->resolved_virtual_tour_video_url ?? $detail?->virtual_tour_video_url,
                'brochure_url' => $detail?->resolved_brochure_pdf_url ?? $detail?->brochure_pdf_url,
                'tours' => $normalizedTours,
            ],
            'espacios' => [
                'eyebrow' => $detail?->espacios_eyebrow ?? 'Espacios',
                'title' => $detail?->espacios_title ?? 'Espacios Comunes',
                'description' => $detail?->espacios_description ?? '',
                'bg_image_url' => $detail?->resolved_espacios_bg_image_url,
                'items' => $espaciosItems ?? [],
                'video' => $detail?->espacios_video ?? ($videoInterstitials[2] ?? null),
                'carousel_spacing' => $detail?->carouselSpacing('espacios'),
            ],
            'brochure_pdf_url' => $detail?->resolved_brochure_pdf_url ?? $detail?->brochure_pdf_url,
            'map' => [
                'eyebrow' => $detail?->map_eyebrow ?? 'Ubicación',
                'title' => $detail?->map_title ?? 'Entorno Inigualable',
                'description' => $detail?->map_description ?? $detail?->entorno_description,
                'bg_color' => $detail?->map_bg_color,
                'logo_url' => $detail?->resolved_map_logo_url,
                'btn_text' => $detail?->map_btn_text ?? 'Experiencias',
                'address' => $detail?->map_address ?? $proyecto->direccion,
                'image_url' => $detail?->resolved_entorno_map_image_url,
                'pin' => [
                    'x' => $detail?->map_pin_x ?? 33.3,
                    'y' => $detail?->map_pin_y ?? 72.4,
                    'logo_url' => $detail?->resolved_map_pin_logo_url,
                ],
                'pois' => $mapPois ?? [],
            ],
            'entorno' => [
                'description' => $detail?->entorno_description,
                'map_image_url' => $detail?->resolved_entorno_map_image_url,
                'bg_color' => $detail?->map_bg_color,
            ],
            'interiorismo' => [
                'eyebrow' => $detail?->interiorismo_eyebrow ?? 'Interiorismo',
                'title' => $detail?->interiorismo_title ?? 'Interiorismo',
                'description' => $detail?->interiorismo_description ?? '',
                'bg_image_url' => $detail?->resolved_interiorismo_bg_image_url,
                'name_position' => $detail?->interiorismo_name_position ?? 'overlay',
                'designers' => $designers,
            ],
            'designers' => $designers,
            'panoramas' => [
                'bg_image_url' => $detail?->resolved_panoramas_bg_image_url,
                'categories' => $panoramasCategories,
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
                    'avatar' => $a->resolved_avatar_url ?? $a->avatar_url ?? $a->avatar_manual_url ?? '',
                    'avatar_url' => $a->resolved_avatar_url ?? $a->avatar_url ?? $a->avatar_manual_url ?? '',
                    'resolved_avatar_url' => $a->resolved_avatar_url ?? $a->avatar_url ?? $a->avatar_manual_url ?? '',
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
            'gallery' => ! empty($proyecto->resolved_gallery) ? $proyecto->resolved_gallery : $galleries->map(fn (ProjectGallery $g): array => [
                'id' => $g->id,
                'category' => $g->category ?: 'general',
                'url' => $g->resolved_image_url,
                'thumbnail_url' => $g->resolved_thumbnail_url,
                'title' => $g->title,
                'alt' => $g->alt_text ?? $g->title,
                'sort_order' => (int) $g->sort_order,
            ])->values()->all(),
            'galleries' => ! empty($proyecto->resolved_gallery) ? $proyecto->resolved_gallery : $galleries->map(fn (ProjectGallery $g): array => [
                'id' => $g->id,
                'category' => $g->category ?: 'general',
                'url' => $g->resolved_image_url,
                'thumbnail_url' => $g->resolved_thumbnail_url,
                'title' => $g->title,
                'alt' => $g->alt_text ?? $g->title,
                'sort_order' => (int) $g->sort_order,
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
