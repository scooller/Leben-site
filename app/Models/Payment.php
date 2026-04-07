<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Support\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use LogsModelActivity;

    protected $fillable = [
        'user_id',
        'project_id',
        'plant_id',
        'gateway',
        'gateway_tx_id',
        'amount',
        'currency',
        'status',
        'metadata',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'completed_at' => 'datetime',
            'gateway' => PaymentGateway::class,
            'status' => PaymentStatus::class,
        ];
    }

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el proyecto (para Transbank Mall - código único por proyecto)
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    /**
     * Relación con la planta.
     */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    /**
     * Scopes
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [
            PaymentStatus::COMPLETED,
            PaymentStatus::AUTHORIZED,
        ]);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [
            PaymentStatus::PENDING,
            PaymentStatus::PROCESSING,
            PaymentStatus::PENDING_APPROVAL,
        ]);
    }

    public function scopeByGateway($query, PaymentGateway|string $gateway)
    {
        $gatewayValue = $gateway instanceof PaymentGateway ? $gateway->value : $gateway;

        return $query->where('gateway', $gatewayValue);
    }

    public function scopeFailed($query)
    {
        return $query->whereIn('status', [
            PaymentStatus::FAILED,
            PaymentStatus::CANCELLED,
            PaymentStatus::EXPIRED,
        ]);
    }

    /**
     * Métodos helper
     */
    public function isCompleted(): bool
    {
        return $this->status->isCompleted();
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    public function canBeRefunded(): bool
    {
        return $this->status->canBeRefunded();
    }

    public function canBeApproved(): bool
    {
        return $this->status->canBeApproved();
    }

    public function requiresManualApproval(): bool
    {
        return $this->gateway->requiresManualApproval();
    }

    /**
     * Marcar como completado
     */
    public function markAsCompleted(): bool
    {
        return $this->update([
            'status' => PaymentStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Marcar como fallido
     */
    public function markAsFailed(string $reason = ''): bool
    {
        $metadata = $this->metadata ?? [];
        $metadata['failure_reason'] = $reason;

        return $this->update([
            'status' => PaymentStatus::FAILED,
            'metadata' => $metadata,
        ]);
    }
}
