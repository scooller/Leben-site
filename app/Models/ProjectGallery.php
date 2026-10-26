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

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
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
