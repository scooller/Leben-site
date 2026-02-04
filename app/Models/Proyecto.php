<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proyecto extends Model
{
    protected $table = 'proyectos';

    protected $fillable = [
        'salesforce_id',
        'name',
        'descripcion',
        'direccion',
        'comuna',
        'provincia',
        'region',
        'email',
        'telefono',
        'pagina_web',
        'razon_social',
        'rut',
        'fecha_inicio_ventas',
        'fecha_entrega',
        'etapa',
        'horario_atencion',
        'dscto_m_x_prod_principal_porc',
        'dscto_m_x_prod_principal_uf',
        'dscto_m_x_bodega_porc',
        'dscto_m_x_bodega_uf',
        'dscto_m_x_estac_porc',
        'dscto_m_x_estac_uf',
        'dscto_max_otros_porc',
        'dscto_max_otros_prod_uf',
        'dscto_maximo_aporte_leben',
        'n_anos_1',
        'n_anos_2',
        'n_anos_3',
        'n_anos_4',
        'valor_reserva_exigido_defecto_peso',
        'valor_reserva_exigido_min_peso',
        'tasa',
        'entrega_inmediata',
    ];

    protected $casts = [
        'fecha_inicio_ventas' => 'date',
        'dscto_m_x_prod_principal_porc' => 'decimal:2',
        'dscto_m_x_prod_principal_uf' => 'decimal:2',
        'dscto_m_x_bodega_porc' => 'decimal:2',
        'dscto_m_x_bodega_uf' => 'decimal:2',
        'dscto_m_x_estac_porc' => 'decimal:2',
        'dscto_m_x_estac_uf' => 'decimal:2',
        'dscto_max_otros_porc' => 'decimal:2',
        'dscto_max_otros_prod_uf' => 'decimal:2',
        'dscto_maximo_aporte_leben' => 'decimal:2',
        'valor_reserva_exigido_defecto_peso' => 'decimal:2',
        'valor_reserva_exigido_min_peso' => 'decimal:2',
        'tasa' => 'decimal:6',
        'entrega_inmediata' => 'boolean',
    ];

    /**
     * Relación con Plantas
     */
    public function plantas()
    {
        return $this->hasMany(Plant::class, 'salesforce_proyecto_id', 'salesforce_id');
    }

    /**
     * Alcance: obtener proyectos activos
     */
    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Alcance: obtener proyectos por etapa
     */
    public function scopeByEtapa($query, string $etapa)
    {
        return $query->where('etapa', $etapa);
    }

    /**
     * Alcance: obtener proyectos por región
     */
    public function scopeByRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    /**
     * Alcance: obtener proyectos por comuna
     */
    public function scopeByComuna($query, string $comuna)
    {
        return $query->where('comuna', $comuna);
    }
}
