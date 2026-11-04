<?php

namespace App\Models;

use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectGallery extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_detail_id',
        'category',
        'title',
        'image_id',
        'image_url',
        'thumbnail_url',
        'alt_text',
        'sort_order',
    ];

    protected $attributes = [
        'category' => 'general',
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProjectGallery $gallery): void {
            if (empty($gallery->category)) {
                $gallery->category = 'general';
            }

            if (! empty($gallery->image_id) && empty($gallery->image_url)) {
                $media = Media::find($gallery->image_id);
                if ($media) {
                    $gallery->image_url = $media->url;
                }
            }
        });
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(ProjectDetail::class, 'project_detail_id');
    }

    public function imageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    public function getResolvedImageUrlAttribute(): ?string
    {
        return $this->imageMedia?->url ?? $this->image_url;
    }

    public function getResolvedThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_url ?? $this->resolved_image_url;
    }
}
