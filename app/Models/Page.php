<?php

namespace App\Models;

use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'badge',
        'hero_image_id',
        'hero_image_url',
        'hero_video_url',
        'seo_title',
        'seo_description',
        'seo_og_image_id',
        'seo_og_image_url',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_image_id');
    }

    public function seoOgImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'seo_og_image_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('sort_order');
    }

    public function activeSections(): HasMany
    {
        return $this->sections()->where('is_active', true);
    }

    public function getResolvedHeroImageUrlAttribute(): ?string
    {
        return $this->heroMedia?->url ?? $this->hero_image_url;
    }

    public function getResolvedSeoOgImageUrlAttribute(): ?string
    {
        return $this->seoOgImageMedia?->url ?? $this->seo_og_image_url;
    }
}
