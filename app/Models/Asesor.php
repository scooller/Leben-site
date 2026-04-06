<?php

namespace App\Models;

use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Asesor extends Model
{
    /** @use HasFactory<\Database\Factories\AsesorFactory> */
    use HasFactory;

    protected $table = 'asesores';

    protected $fillable = [
        'salesforce_id',
        'first_name',
        'last_name',
        'email',
        'whatsapp_owner',
        'avatar_url',
        'avatar_image_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function proyectos(): BelongsToMany
    {
        return $this->belongsToMany(Proyecto::class, 'asesor_proyecto')
            ->withTimestamps();
    }

    public function avatarImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'avatar_image_id');
    }

    protected function resolvedAvatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatarImageMedia?->url ?: $this->avatar_url);
    }

    public function getFullNameAttribute(): string
    {
        $fullName = trim(implode(' ', array_filter([$this->first_name, $this->last_name])));

        if ($fullName !== '') {
            return $fullName;
        }

        if (filled($this->email)) {
            return (string) $this->email;
        }

        return $this->salesforce_id ?? 'Asesor #'.$this->id;
    }
}
