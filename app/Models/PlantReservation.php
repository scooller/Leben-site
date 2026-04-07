<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Support\LogsModelActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantReservation extends Model
{
    use HasFactory;
    use LogsModelActivity;

    protected $fillable = [
        'plant_id',
        'user_id',
        'session_token',
        'status',
        'expires_at',
        'completed_at',
        'released_at',
        'released_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'released_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Relacion con la planta
     */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    /**
     * Relacion con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: solo reservas activas (no expiradas)
     */
    public function scopeActive($query)
    {
        return $query->where('status', ReservationStatus::ACTIVE)
            ->where('expires_at', '>', now());
    }

    /**
     * Scope: reservas expiradas pero aun marcadas como activas
     */
    public function scopeExpiredButActive($query)
    {
        return $query->where('status', ReservationStatus::ACTIVE)
            ->where('expires_at', '<', now());
    }

    /**
     * Scope: filtrar por planta
     */
    public function scopeForPlant($query, int $plantId)
    {
        return $query->where('plant_id', $plantId);
    }

    /**
     * Scope: buscar por session token
     */
    public function scopeBySessionToken($query, string $token)
    {
        return $query->where('session_token', $token);
    }

    /**
     * Verificar si la reserva esta activa y no expirada
     */
    public function isActive(): bool
    {
        return $this->status->isActive() && $this->expires_at->isFuture();
    }

    /**
     * Verificar si la reserva expiro (sigue marcada active pero el tiempo paso)
     */
    public function isExpired(): bool
    {
        return $this->status->isActive() && $this->expires_at->isPast();
    }

    /**
     * Obtener segundos restantes antes de expirar
     */
    public function remainingSeconds(): int
    {
        if (! $this->isActive()) {
            return 0;
        }

        return (int) max(0, now()->diffInSeconds($this->expires_at, false));
    }

    /**
     * Marcar como completada (pago exitoso)
     */
    public function markAsCompleted(): bool
    {
        return $this->update([
            'status' => ReservationStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Marcar como expirada
     */
    public function markAsExpired(): bool
    {
        return $this->update([
            'status' => ReservationStatus::EXPIRED,
            'released_at' => now(),
            'released_by' => 'system',
        ]);
    }

    /**
     * Liberar la reserva
     */
    public function release(string $releasedBy = 'user', ?string $reason = null): bool
    {
        $metadata = $this->metadata ?? [];
        if ($reason) {
            $metadata['release_reason'] = $reason;
        }

        return $this->update([
            'status' => ReservationStatus::RELEASED,
            'released_at' => now(),
            'released_by' => $releasedBy,
            'metadata' => $metadata,
        ]);
    }
}
