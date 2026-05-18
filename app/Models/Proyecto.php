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

    /**
     * @var array<string, string>
     */
    public const ETAPA_OPTIONS = [
        'permiso_edificacion' => 'Permiso de edificación',
        'demolicion' => 'Demolición',
        'inicio_obra' => 'Inicio de obra',
        'excavacion_masiva' => 'Excavación masiva',
        'obra_gruesa' => 'Obra gruesa',
        'terminaciones' => 'Terminaciones',
        'recepcion_municipal_y_copropiedad' => 'Recepción Municipal y Copropiedad',
        'escrituracion' => 'Escrituración',
        'entrega' => 'Entrega',
        'postventa' => 'Postventa',
    ];

    /**
     * @var array<string, string>
     */
    public const ETAPA_ALIASES = [
        'permiso_de_edificacion' => 'permiso_edificacion',
        'inicio_de_obra' => 'inicio_obra',
        'construccion' => 'obra_gruesa',
        'entrega_inmediata' => 'entrega',
        'preventa' => 'permiso_edificacion',
        'venta' => 'inicio_obra',
    ];

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
        'valor_reserva_exigido_defecto_peso',
        'valor_reserva_exigido_min_peso',
        'descuento_defecto_cotizacion_web',
        'descuento_maximo_cotizacion_web',
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
        'valor_reserva_exigido_defecto_peso' => 'decimal:2',
        'valor_reserva_exigido_min_peso' => 'decimal:2',
        'descuento_defecto_cotizacion_web' => 'decimal:2',
        'descuento_maximo_cotizacion_web' => 'decimal:2',
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
     * @return array<string, string>
     */
    public static function etapaOptions(): array
    {
        return self::ETAPA_OPTIONS;
    }

    public static function normalizeEtapa(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        if (array_key_exists($raw, self::ETAPA_OPTIONS)) {
            return $raw;
        }

        $normalizedKey = self::normalizeEtapaKey($raw);

        if ($normalizedKey === '') {
            return null;
        }

        if (array_key_exists($normalizedKey, self::ETAPA_OPTIONS)) {
            return $normalizedKey;
        }

        $aliasMatch = self::ETAPA_ALIASES[$normalizedKey] ?? null;
        if ($aliasMatch !== null) {
            return $aliasMatch;
        }

        // Fallback robusto para textos con codificación irregular (ej: "edificaciÃ³n").
        return self::matchEtapaByKeywords($normalizedKey);
    }

    public static function etapaLabel(mixed $value): ?string
    {
        $normalized = self::normalizeEtapa($value);

        if ($normalized === null) {
            return null;
        }

        return self::ETAPA_OPTIONS[$normalized] ?? null;
    }

    private static function normalizeEtapaKey(string $value): string
    {
        $ascii = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');

        return (string) $ascii;
    }

    private static function matchEtapaByKeywords(string $normalizedKey): ?string
    {
        if (str_contains($normalizedKey, 'permiso') && str_contains($normalizedKey, 'edific')) {
            return 'permiso_edificacion';
        }

        if (str_contains($normalizedKey, 'demol')) {
            return 'demolicion';
        }

        if (str_contains($normalizedKey, 'inicio') && str_contains($normalizedKey, 'obra')) {
            return 'inicio_obra';
        }

        if (str_contains($normalizedKey, 'excav')) {
            return 'excavacion_masiva';
        }

        if (str_contains($normalizedKey, 'obra') && (str_contains($normalizedKey, 'grues') || str_contains($normalizedKey, 'constru'))) {
            return 'obra_gruesa';
        }

        if (str_contains($normalizedKey, 'termina')) {
            return 'terminaciones';
        }

        if (str_contains($normalizedKey, 'recepcion') && str_contains($normalizedKey, 'municip')) {
            return 'recepcion_municipal_y_copropiedad';
        }

        if (str_contains($normalizedKey, 'escritur')) {
            return 'escrituracion';
        }

        if (str_contains($normalizedKey, 'entrega')) {
            return 'entrega';
        }

        if (str_contains($normalizedKey, 'postventa')) {
            return 'postventa';
        }

        return null;
    }

    protected function etapa(): Attribute
    {
        return Attribute::make(
            get: fn(?string $value): ?string => self::normalizeEtapa($value) ?? $value,
            set: fn(mixed $value): ?string => self::normalizeEtapa($value) ?? ($this->normalizeRawString($value)),
        );
    }

    private function normalizeRawString(mixed $value): ?string
    {
        $raw = trim((string) $value);

        return $raw === '' ? null : $raw;
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
        return Attribute::get(fn(): string => ProjectImageService::getProjectImageUrl($this));
    }

    /**
     * Obtener el código de comercio Transbank para este proyecto
     * Prioriza el valor persistido en DB y, si no existe,
     * usa el fallback configurado por slug.
     */
    public function getTransbankCommerceCodeAttribute(): ?string
    {
        $storedValue = $this->getRawOriginal('transbank_commerce_code');

        if (filled($storedValue)) {
            return $storedValue;
        }

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
