<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'cover_image_id',
        'interior_image_id',
        'salesforce_interior_image_url',
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

    public function coverImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_image_id');
    }

    public function interiorImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'interior_image_id');
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

    /**
     * Relacion con reservas
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(PlantReservation::class);
    }

    /**
     * Relacion con pagos.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Obtener reserva activa actual (si existe)
     */
    public function activeReservation(): HasOne
    {
        return $this->hasOne(PlantReservation::class)
            ->where('status', ReservationStatus::ACTIVE)
            ->where('expires_at', '>', now())
            ->latest();
    }

    /**
     * Obtener la reserva completada mas reciente (pago exitoso).
     */
    public function completedReservation(): HasOne
    {
        return $this->hasOne(PlantReservation::class)
            ->where('status', ReservationStatus::COMPLETED)
            ->latest('completed_at');
    }

    /**
     * Obtener el pago completado o autorizado mas reciente.
     */
    public function completedPayment(): HasOne
    {
        return $this->hasOne(Payment::class)
            ->whereIn('status', [
                PaymentStatus::COMPLETED,
                PaymentStatus::AUTHORIZED,
            ])
            ->latest('completed_at')
            ->latest('id');
    }

    /**
     * Verificar si la planta esta reservada actualmente
     */
    public function isReserved(): bool
    {
        return $this->activeReservation()->exists();
    }

    /**
     * Verificar si la planta ya fue pagada.
     */
    public function isPaid(): bool
    {
        return $this->completedReservation()->exists() || $this->completedPayment()->exists();
    }
}
