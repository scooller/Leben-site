<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plant extends Model
{
    use HasFactory;

    protected $fillable = [
        'salesforce_product_id',
        'salesforce_proyecto_id',
        'name',
        'product_code',
        'orientacion',
        'programa',
        'programa2',
        'piso',
        'precio_base',
        'precio_lista',
        'superficie_total_principal',
        'superficie_interior',
        'superficie_util',
        'opportunity_id',
        'superficie_terraza',
        'superficie_vendible',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'precio_base' => 'decimal:2',
        'precio_lista' => 'decimal:2',
        'superficie_total_principal' => 'decimal:2',
        'superficie_interior' => 'decimal:2',
        'superficie_util' => 'decimal:2',
        'superficie_terraza' => 'decimal:2',
        'superficie_vendible' => 'decimal:2',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Relación con Proyecto
     */
    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'salesforce_proyecto_id', 'salesforce_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPrograma($query, string $programa)
    {
        return $query->where('programa', $programa);
    }

    public function scopeByPiso($query, string $piso)
    {
        return $query->where('piso', $piso);
    }

    public function scopeByProgramaPiso($query, string $programa, string $piso)
    {
        return $query->where('programa', $programa)->where('piso', $piso);
    }
}
