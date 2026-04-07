<?php

namespace App\Models;

use App\Services\ProjectImageService;
use App\Support\LogsModelActivity;
use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Proyecto extends Model
{
    use HasFactory;
    use LogsModelActivity;

    protected $table = 'proyectos';

    protected $fillable = [
        'salesforce_id',
        'name',
        'slug',
        'tipo',
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
        'is_active',
        'project_image_id',
        'salesforce_logo_url',
        'salesforce_portada_url',
        // Descuentos de mercado
        'dscto_m_x_prod_principal_porc',
        'dscto_m_x_prod_principal_uf',
        'dscto_m_x_bodega_porc',
        'dscto_m_x_bodega_uf',
        'dscto_m_x_estac_porc',
        'dscto_m_x_estac_uf',
        'dscto_max_otros_porc',
        'dscto_max_otros_prod_uf',
        'dscto_maximo_aporte_leben',
        // Configuración de financiamiento
        'n_anos_1',
        'n_anos_2',
        'n_anos_3',
        'n_anos_4',
        'valor_reserva_exigido_defecto_peso',
        'valor_reserva_exigido_min_peso',
        'tasa',
        'entrega_inmediata',
        // Transbank Mall
        'transbank_commerce_code',
        // Pago manual por proyecto
        'manual_payment_instructions',
        'manual_payment_bank_accounts',
        'manual_payment_link',
    ];

    protected $casts = [
        'fecha_inicio_ventas' => 'date',
        'tipo' => 'array',
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
        'is_active' => 'boolean',
        'manual_payment_bank_accounts' => 'array',
    ];

    /**
     * Boot del modelo
     */
    protected static function booted(): void
    {
        // Generar slug automáticamente si no existe
        static::creating(function (self $model) {
            if (! $model->slug) {
                $model->slug = Str::slug($model->name);
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('name') && ! $model->isDirty('slug')) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    /**
     * Relación con imagen del proyecto (Media de Curator)
     */
    public function projectImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'project_image_id');
    }

    /**
     * Atributo computado: obtiene la URL de imagen del proyecto
     * Sigue la prioridad: imagen del proyecto > portada Salesforce > logo principal > ícono por defecto
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): string => ProjectImageService::getProjectImageUrl($this));
    }

    /**
     * Obtener el código de comercio Transbank para este proyecto
     * Busca en la configuración bajo la clave del slug
     */
    public function getTransbankCommerceCodeAttribute(): ?string
    {
        $codes = config('payments.gateways.transbank.commerce_codes', []);

        return $codes[$this->slug] ?? null;
    }

    /**
     * Relación con Pagos
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'project_id');
    }

    /**
     * Relación con asesores del proyecto.
     */
    public function asesores(): BelongsToMany
    {
        return $this->belongsToMany(Asesor::class, 'asesor_proyecto')
            ->withTimestamps();
    }

    public function plantas(): HasMany
    {
        return $this->hasMany(Plant::class, 'salesforce_proyecto_id', 'salesforce_id');
    }

    /**
     * Alcance: obtener proyectos activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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

    /**
     * Alcance: obtener proyectos por tipo
     */
    public function scopeByTipo($query, string $tipo)
    {
        return $query->where(function ($subQuery) use ($tipo) {
            $subQuery->whereJsonContains('tipo', $tipo)
                ->orWhere('tipo', $tipo);
        });
    }
}
