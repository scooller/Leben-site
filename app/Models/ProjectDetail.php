<?php

namespace App\Models;

use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'custom_css',
        'custom_js',
        'hero_eyebrow',
        'hero_title',
        'hero_subtitle',
        'hero_video_url',
        'hero_video_media_id',
        'hero_logo_id',
        'hero_logo_url',
        'theme_colors',
        'technical_info',
        'project_eyebrow',
        'project_title',
        'project_description',
        'project_bg_image_id',
        'project_bg_image_url',
        'project_highlight_offer',
        'project_slides',
        'proyecto_video',
        'video_interstitials',
        'equipment_eyebrow',
        'equipment_title',
        'equipment_description',
        'equipment_bg_image_id',
        'equipment_bg_image_url',
        'equipment_brands',
        'equipment_slides',
        'equipment_spaces_modal',
        'equipamiento_video',
        'overview_title',
        'overview_description',
        'overview_images',
        'overview_stats',
        'terminaciones_title',
        'terminaciones_description',
        'terminaciones_items',
        'virtual_tour_matterport_url',
        'virtual_tour_360_url',
        'virtual_tour_360_media_id',
        'virtual_tour_video_url',
        'virtual_tour_video_media_id',
        'virtual_tours_eyebrow',
        'virtual_tours_title',
        'virtual_tours_config',
        'brochure_pdf_url',
        'brochure_media_id',
        'espacios_eyebrow',
        'espacios_title',
        'espacios_description',
        'espacios_bg_image_id',
        'espacios_bg_image_url',
        'espacios_items',
        'espacios_video',
        'designers',
        'panoramas',
        'panoramas_categories',
        'panoramas_bg_image_id',
        'panoramas_bg_image_url',
        'entorno_description',
        'entorno_map_image_id',
        'entorno_map_image_url',
        'map_eyebrow',
        'map_title',
        'map_description',
        'map_logo_id',
        'map_logo_url',
        'map_btn_text',
        'map_address',
        'map_pin_x',
        'map_pin_y',
        'map_pin_logo_id',
        'map_pin_logo_url',
        'map_pois',
        'map_bg_color',
        'carousel_spacing',
        'interiorismo_eyebrow',
        'interiorismo_title',
        'interiorismo_description',
        'interiorismo_bg_image_id',
        'interiorismo_bg_image_url',
        'interiorismo_name_position',
        'contact_eyebrow',
        'contact_title',
        'contact_subtitle',
        'contact_waze_url',
        'schedule',
        'announcement',
    ];

    protected function casts(): array
    {
        return [
            'theme_colors' => 'array',
            'technical_info' => 'array',
            'project_slides' => 'array',
            'proyecto_video' => 'array',
            'video_interstitials' => 'array',
            'equipment_brands' => 'array',
            'equipment_slides' => 'array',
            'equipment_spaces_modal' => 'array',
            'equipamiento_video' => 'array',
            'overview_images' => 'array',
            'overview_stats' => 'array',
            'terminaciones_items' => 'array',
            'virtual_tours_config' => 'array',
            'espacios_items' => 'array',
            'espacios_video' => 'array',
            'designers' => 'array',
            'panoramas' => 'array',
            'panoramas_categories' => 'array',
            'map_pois' => 'array',
            'carousel_spacing' => 'array',
            'map_pin_x' => 'float',
            'map_pin_y' => 'float',
            'schedule' => 'array',
            'announcement' => 'array',
        ];
    }

    /**
     * Espaciado top/bottom del carrusel de una sección. Número sin unidad = px; vacío = 0.
     *
     * @return array{top: string, bottom: string}
     */
    public function carouselSpacing(string $section): array
    {
        $normalize = static function (mixed $value): string {
            $value = trim((string) $value);

            return match (true) {
                $value === '' => '0',
                (bool) preg_match('/^\d+(\.\d+)?$/', $value) => $value.'px',
                default => $value,
            };
        };

        return [
            'top' => $normalize($this->carousel_spacing[$section]['top'] ?? ''),
            'bottom' => $normalize($this->carousel_spacing[$section]['bottom'] ?? ''),
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProjectDetail $detail): void {
            if (! empty($detail->hero_video_media_id)) {
                $resolved = static::resolveMediaUrl($detail->hero_video_media_id);
                if ($resolved && (empty($detail->hero_video_url) || $detail->isDirty('hero_video_media_id'))) {
                    $detail->hero_video_url = $resolved;
                }
            }

            if (! empty($detail->hero_logo_id)) {
                $resolved = static::resolveMediaUrl($detail->hero_logo_id);
                if ($resolved && (empty($detail->hero_logo_url) || $detail->isDirty('hero_logo_id'))) {
                    $detail->hero_logo_url = $resolved;
                }
            }

            if (! empty($detail->project_bg_image_id)) {
                $resolved = static::resolveMediaUrl($detail->project_bg_image_id);
                if ($resolved && (empty($detail->project_bg_image_url) || $detail->isDirty('project_bg_image_id'))) {
                    $detail->project_bg_image_url = $resolved;
                }
            }

            if (! empty($detail->virtual_tour_360_media_id)) {
                $resolved = static::resolveMediaUrl($detail->virtual_tour_360_media_id);
                if ($resolved && (empty($detail->virtual_tour_360_url) || $detail->isDirty('virtual_tour_360_media_id'))) {
                    $detail->virtual_tour_360_url = $resolved;
                }
            }

            if (! empty($detail->virtual_tour_video_media_id)) {
                $resolved = static::resolveMediaUrl($detail->virtual_tour_video_media_id);
                if ($resolved && (empty($detail->virtual_tour_video_url) || $detail->isDirty('virtual_tour_video_media_id'))) {
                    $detail->virtual_tour_video_url = $resolved;
                }
            }

            if (! empty($detail->brochure_media_id)) {
                $resolved = static::resolveMediaUrl($detail->brochure_media_id);
                if ($resolved && (empty($detail->brochure_pdf_url) || $detail->isDirty('brochure_media_id'))) {
                    $detail->brochure_pdf_url = $resolved;
                }
            }

            if (! empty($detail->equipment_bg_image_id)) {
                $resolved = static::resolveMediaUrl($detail->equipment_bg_image_id);
                if ($resolved && (empty($detail->equipment_bg_image_url) || $detail->isDirty('equipment_bg_image_id'))) {
                    $detail->equipment_bg_image_url = $resolved;
                }
            }

            if (! empty($detail->espacios_bg_image_id)) {
                $resolved = static::resolveMediaUrl($detail->espacios_bg_image_id);
                if ($resolved && (empty($detail->espacios_bg_image_url) || $detail->isDirty('espacios_bg_image_id'))) {
                    $detail->espacios_bg_image_url = $resolved;
                }
            }

            if (! empty($detail->panoramas_bg_image_id)) {
                $resolved = static::resolveMediaUrl($detail->panoramas_bg_image_id);
                if ($resolved && (empty($detail->panoramas_bg_image_url) || $detail->isDirty('panoramas_bg_image_id'))) {
                    $detail->panoramas_bg_image_url = $resolved;
                }
            }

            if (! empty($detail->interiorismo_bg_image_id)) {
                $resolved = static::resolveMediaUrl($detail->interiorismo_bg_image_id);
                if ($resolved && (empty($detail->interiorismo_bg_image_url) || $detail->isDirty('interiorismo_bg_image_id'))) {
                    $detail->interiorismo_bg_image_url = $resolved;
                }
            }

            if (! empty($detail->entorno_map_image_id)) {
                $resolved = static::resolveMediaUrl($detail->entorno_map_image_id);
                if ($resolved && (empty($detail->entorno_map_image_url) || $detail->isDirty('entorno_map_image_id'))) {
                    $detail->entorno_map_image_url = $resolved;
                }
            }

            if (! empty($detail->map_logo_id)) {
                $resolved = static::resolveMediaUrl($detail->map_logo_id);
                if ($resolved && (empty($detail->map_logo_url) || $detail->isDirty('map_logo_id'))) {
                    $detail->map_logo_url = $resolved;
                }
            }

            if (! empty($detail->map_pin_logo_id)) {
                $resolved = static::resolveMediaUrl($detail->map_pin_logo_id);
                if ($resolved && (empty($detail->map_pin_logo_url) || $detail->isDirty('map_pin_logo_id'))) {
                    $detail->map_pin_logo_url = $resolved;
                }
            }

            if (! empty($detail->virtual_tours_config) && is_array($detail->virtual_tours_config)) {
                $tours = $detail->virtual_tours_config;
                foreach ($tours as &$tour) {
                    $tourMediaId = $tour['media_id'] ?? $tour['src_media_id'] ?? null;
                    if (! empty($tourMediaId) && empty($tour['src'])) {
                        $tourMedia = Media::find($tourMediaId);
                        if ($tourMedia) {
                            $tour['src'] = $tourMedia->url;
                        }
                    }

                    if (! empty($tour['interiores']) && is_array($tour['interiores'])) {
                        foreach ($tour['interiores'] as &$interior) {
                            $intMediaId = $interior['media_id'] ?? $interior['src_media_id'] ?? null;
                            if (! empty($intMediaId) && empty($interior['src'])) {
                                $intMedia = Media::find($intMediaId);
                                if ($intMedia) {
                                    $interior['src'] = $intMedia->url;
                                }
                            }
                        }
                    }
                }
                $detail->virtual_tours_config = $tours;
            }

            if (! empty($detail->proyecto_video) && is_array($detail->proyecto_video)) {
                $pVideo = $detail->proyecto_video;
                if (! empty($pVideo['video_media_id']) && empty($pVideo['video_url'])) {
                    $pVideo['video_url'] = static::resolveMediaUrl($pVideo['video_media_id']);
                }
                $detail->proyecto_video = $pVideo;
            }

            if (! empty($detail->equipamiento_video) && is_array($detail->equipamiento_video)) {
                $eqVideo = $detail->equipamiento_video;
                if (! empty($eqVideo['video_media_id']) && empty($eqVideo['video_url'])) {
                    $eqVideo['video_url'] = static::resolveMediaUrl($eqVideo['video_media_id']);
                }
                $detail->equipamiento_video = $eqVideo;
            }

            if (! empty($detail->espacios_video) && is_array($detail->espacios_video)) {
                $espVideo = $detail->espacios_video;
                if (! empty($espVideo['video_media_id']) && empty($espVideo['video_url'])) {
                    $espVideo['video_url'] = static::resolveMediaUrl($espVideo['video_media_id']);
                }
                $detail->espacios_video = $espVideo;
            }

            $interstitials = $detail->video_interstitials ?? [];

            if (! empty($detail->proyecto_video)) {
                $interstitials[0] = array_merge($interstitials[0] ?? [], $detail->proyecto_video);
            } elseif (! empty($interstitials[0]) && empty($detail->proyecto_video)) {
                $detail->proyecto_video = $interstitials[0];
            }

            if (! empty($detail->equipamiento_video)) {
                $interstitials[1] = array_merge($interstitials[1] ?? [], $detail->equipamiento_video);
            } elseif (! empty($interstitials[1]) && empty($detail->equipamiento_video)) {
                $detail->equipamiento_video = $interstitials[1];
            }

            if (! empty($detail->espacios_video)) {
                $interstitials[2] = array_merge($interstitials[2] ?? [], $detail->espacios_video);
            } elseif (! empty($interstitials[2]) && empty($detail->espacios_video)) {
                $detail->espacios_video = $interstitials[2];
            }

            foreach ($interstitials as &$item) {
                if (is_array($item)) {
                    $vMediaId = $item['video_media_id'] ?? null;
                    if (! empty($vMediaId) && empty($item['video_url'])) {
                        $item['video_url'] = static::resolveMediaUrl($vMediaId);
                    }
                }
            }

            $detail->video_interstitials = $interstitials;

            if (! empty($detail->project_slides) && is_array($detail->project_slides)) {
                $slides = $detail->project_slides;
                foreach ($slides as &$slide) {
                    if (empty($slide['img']) && ! empty($slide['media_id'])) {
                        $slide['img'] = static::resolveMediaUrl($slide['media_id']);
                    }
                }
                $detail->project_slides = $slides;
            }

            if (! empty($detail->equipment_brands) && is_array($detail->equipment_brands)) {
                $brands = $detail->equipment_brands;
                foreach ($brands as &$brand) {
                    if (empty($brand['src']) && ! empty($brand['media_id'])) {
                        $brand['src'] = static::resolveMediaUrl($brand['media_id']);
                    }
                }
                $detail->equipment_brands = $brands;
            }

            if (! empty($detail->equipment_slides) && is_array($detail->equipment_slides)) {
                $eqSlides = $detail->equipment_slides;
                foreach ($eqSlides as &$slide) {
                    if (empty($slide['img']) && ! empty($slide['media_id'])) {
                        $slide['img'] = static::resolveMediaUrl($slide['media_id']);
                    }
                }
                $detail->equipment_slides = $eqSlides;
            }

            if (! empty($detail->equipment_spaces_modal) && is_array($detail->equipment_spaces_modal)) {
                $spaces = $detail->equipment_spaces_modal;
                foreach ($spaces as &$space) {
                    if (! empty($space['images']) && is_array($space['images'])) {
                        foreach ($space['images'] as &$image) {
                            if (empty($image['img']) && ! empty($image['media_id'])) {
                                $image['img'] = static::resolveMediaUrl($image['media_id']);
                            }
                        }
                    }
                }
                $detail->equipment_spaces_modal = $spaces;
            }

            if (! empty($detail->espacios_items) && is_array($detail->espacios_items)) {
                $espacios = $detail->espacios_items;
                foreach ($espacios as &$item) {
                    if (empty($item['img']) && ! empty($item['media_id'])) {
                        $item['img'] = static::resolveMediaUrl($item['media_id']);
                    }
                }
                $detail->espacios_items = $espacios;
            }

            if (! empty($detail->map_pois) && is_array($detail->map_pois)) {
                $pois = $detail->map_pois;
                foreach ($pois as &$poi) {
                    if (empty($poi['img']) && ! empty($poi['media_id'])) {
                        $poi['img'] = static::resolveMediaUrl($poi['media_id']);
                    }
                }
                $detail->map_pois = $pois;
            }

            if (! empty($detail->panoramas_categories) && is_array($detail->panoramas_categories)) {
                $categories = $detail->panoramas_categories;
                foreach ($categories as &$cat) {
                    if (! empty($cat['slides']) && is_array($cat['slides'])) {
                        foreach ($cat['slides'] as &$slide) {
                            if (empty($slide['image']) && ! empty($slide['media_id'])) {
                                $slide['image'] = static::resolveMediaUrl($slide['media_id']);
                            }
                        }
                    }
                }
                $detail->panoramas_categories = $categories;
            }

            if (! empty($detail->designers) && is_array($detail->designers)) {
                $designers = $detail->designers;
                foreach ($designers as &$designer) {
                    if (empty($designer['image']) && ! empty($designer['media_id'])) {
                        $designer['image'] = static::resolveMediaUrl($designer['media_id']);
                    }
                }
                $detail->designers = $designers;
            }
        });
    }

    public static function resolveMediaUrl(mixed $mediaId): ?string
    {
        if (blank($mediaId)) {
            return null;
        }

        if (is_array($mediaId)) {
            $first = reset($mediaId);
            if (is_array($first)) {
                if (! empty($first['url'])) {
                    return $first['url'];
                }
                $mediaId = $first['id'] ?? null;
            } else {
                if (! empty($mediaId['url'])) {
                    return $mediaId['url'];
                }
                $mediaId = $mediaId['id'] ?? $first;
            }
        }

        return is_numeric($mediaId) ? Media::find($mediaId)?->url : null;
    }

    public function brochureMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'brochure_media_id');
    }

    public function getResolvedBrochurePdfUrlAttribute(): ?string
    {
        return $this->brochureMedia?->url ?? $this->brochure_pdf_url;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'project_id');
    }

    public function equipmentBgImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'equipment_bg_image_id');
    }

    public function projectBgImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'project_bg_image_id');
    }

    public function espaciosBgImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'espacios_bg_image_id');
    }

    public function interiorismoBgImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'interiorismo_bg_image_id');
    }

    public function heroVideoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_video_media_id');
    }

    public function virtualTour360Media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'virtual_tour_360_media_id');
    }

    public function virtualTourVideoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'virtual_tour_video_media_id');
    }

    public function heroLogoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_logo_id');
    }

    public function entornoMapMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'entorno_map_image_id');
    }

    public function mapLogoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'map_logo_id');
    }

    public function mapPinLogoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'map_pin_logo_id');
    }

    public function panoramasBgImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'panoramas_bg_image_id');
    }

    public function amenities(): HasMany
    {
        return $this->hasMany(ProjectAmenity::class)->orderBy('sort_order');
    }

    public function pois(): HasMany
    {
        return $this->hasMany(ProjectPoi::class)->orderBy('sort_order');
    }

    public function galleries(): HasMany
    {
        return $this->hasMany(ProjectGallery::class)->orderBy('sort_order');
    }

    public function getResolvedHeroVideoUrlAttribute(): ?string
    {
        return $this->heroVideoMedia?->url ?? $this->hero_video_url;
    }

    public function getResolvedVirtualTour360UrlAttribute(): ?string
    {
        return $this->virtualTour360Media?->url ?? $this->virtual_tour_360_url;
    }

    public function getResolvedVirtualTourVideoUrlAttribute(): ?string
    {
        return $this->virtualTourVideoMedia?->url ?? $this->virtual_tour_video_url;
    }

    public function getResolvedProjectBgImageUrlAttribute(): ?string
    {
        return $this->projectBgImageMedia?->url ?? $this->project_bg_image_url;
    }

    public function getResolvedEquipmentBgImageUrlAttribute(): ?string
    {
        return $this->equipmentBgImageMedia?->url ?? $this->equipment_bg_image_url;
    }

    public function getResolvedEspaciosBgImageUrlAttribute(): ?string
    {
        return $this->espaciosBgImageMedia?->url ?? $this->espacios_bg_image_url;
    }

    public function getResolvedInteriorismoBgImageUrlAttribute(): ?string
    {
        return $this->interiorismoBgImageMedia?->url ?? $this->interiorismo_bg_image_url;
    }

    public function getResolvedHeroLogoUrlAttribute(): ?string
    {
        return $this->heroLogoMedia?->url ?? $this->hero_logo_url;
    }

    public function getResolvedEntornoMapImageUrlAttribute(): ?string
    {
        return $this->entornoMapMedia?->url ?? $this->entorno_map_image_url;
    }

    public function getResolvedMapLogoUrlAttribute(): ?string
    {
        return $this->mapLogoMedia?->url ?? $this->map_logo_url;
    }

    public function getResolvedMapPinLogoUrlAttribute(): ?string
    {
        return $this->mapPinLogoMedia?->url ?? $this->map_pin_logo_url;
    }

    public function getResolvedPanoramasBgImageUrlAttribute(): ?string
    {
        return $this->panoramasBgImageMedia?->url ?? $this->panoramas_bg_image_url;
    }

    /**
     * Devuelve valores por defecto estructurados a partir de los datos Salesforce del proyecto.
     *
     * @return array<string, mixed>
     */
    public static function getDefaultsFromProyecto(Proyecto $record): array
    {
        $tech = [];
        if (filled($record->direccion) || filled($record->comuna)) {
            $dir = trim(($record->direccion ?? '').(filled($record->comuna) ? ', '.$record->comuna : ''));
            $tech[] = ['id' => 'direccion', 'label' => 'Dirección', 'value' => $dir];
        }
        if (filled($record->etapa)) {
            $tech[] = ['id' => 'estado', 'label' => 'Estado del proyecto', 'value' => ucwords(str_replace('_', ' ', (string) $record->etapa))];
        }
        if (filled($record->horario_atencion)) {
            $tech[] = ['id' => 'horario', 'label' => 'Horario de Atención', 'value' => $record->horario_atencion];
        }

        $locationParts = array_filter([$record->direccion, $record->comuna, $record->region]);

        return [
            'hero_eyebrow' => $record->comuna ?: $record->region,
            'hero_title' => $record->name,
            'hero_subtitle' => filled($record->comuna)
                ? "Conexión Urbana & Paisajismo en {$record->comuna}"
                : 'Conexión Urbana & Paisajismo',
            'hero_logo_url' => $record->salesforce_logo_url,
            'theme_colors' => [
                'accent' => '#d9bc70',
                'accent_dark' => '#c4a355',
                'accent_light' => '#e2ca8d',
                'gradient' => 'linear-gradient(135deg, #d9bc70 0%, #b89345 100%)',
            ],
            'technical_info' => $tech,
            'overview_title' => $record->name,
            'overview_description' => $record->descripcion,
            'entorno_description' => ! empty($locationParts)
                ? 'Ubicado en '.implode(', ', $locationParts).'.'
                : null,
            'schedule' => filled($record->horario_atencion)
                ? [$record->horario_atencion]
                : [],
            'announcement' => [
                'enabled' => false,
                'id' => "aviso-{$record->slug}-2026",
                'title' => "Atención {$record->name}",
                'message' => $record->horario_atencion ?: 'Informamos a nuestros clientes e inversionistas...',
                'button_text' => 'Entendido',
            ],
        ];
    }
}
