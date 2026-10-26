<?php

namespace App\Models;

use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPoi extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_detail_id',
        'name',
        'category',
        'distance',
        'walking_time',
        'image_id',
        'image_url',
        'coordinate_x',
        'coordinate_y',
        'pin_direction',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'coordinate_x' => 'float',
            'coordinate_y' => 'float',
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
}
