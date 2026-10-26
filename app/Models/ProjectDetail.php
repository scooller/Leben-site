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
        'hero_eyebrow',
        'hero_title',
        'hero_subtitle',
        'hero_video_url',
        'hero_logo_id',
        'hero_logo_url',
        'theme_colors',
        'technical_info',
        'project_slides',
        'equipment_brands',
        'equipment_slides',
        'overview_title',
        'overview_description',
        'overview_images',
        'overview_stats',
        'terminaciones_title',
        'terminaciones_description',
        'terminaciones_items',
        'virtual_tour_matterport_url',
        'virtual_tour_360_url',
        'virtual_tour_video_url',
        'brochure_pdf_url',
        'designers',
        'panoramas',
        'entorno_description',
        'entorno_map_image_id',
        'entorno_map_image_url',
        'schedule',
        'announcement',
    ];

    protected function casts(): array
    {
        return [
            'theme_colors' => 'array',
            'technical_info' => 'array',
            'project_slides' => 'array',
            'equipment_brands' => 'array',
            'equipment_slides' => 'array',
            'overview_images' => 'array',
            'overview_stats' => 'array',
            'terminaciones_items' => 'array',
            'designers' => 'array',
            'panoramas' => 'array',
            'schedule' => 'array',
            'announcement' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'project_id');
    }

    public function heroLogoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_logo_id');
    }

    public function entornoMapMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'entorno_map_image_id');
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

    public function getResolvedHeroLogoUrlAttribute(): ?string
    {
        return $this->heroLogoMedia?->url ?? $this->hero_logo_url;
    }

    public function getResolvedEntornoMapImageUrlAttribute(): ?string
    {
        return $this->entornoMapMedia?->url ?? $this->entorno_map_image_url;
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
