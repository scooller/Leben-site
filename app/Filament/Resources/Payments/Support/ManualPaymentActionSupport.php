<?php

namespace App\Filament\Resources\Payments\Support;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\PlantReservationService;
use App\Support\BusinessActivityLogger;
use Illuminate\Support\Facades\DB;

class ManualPaymentActionSupport
{
    /**
     * @return array<string, mixed>
     */
    public static function metadata(Payment $payment): array
    {
        return is_array($payment->metadata) ? $payment->metadata : [];
    }

    public static function isManualPendingApproval(Payment $payment): bool
    {
        if (! $payment->requiresManualApproval()) {
            return false;
        }

        $status = $payment->status instanceof PaymentStatus
            ? $payment->status
            : PaymentStatus::tryFrom((string) $payment->status);

        return $status === PaymentStatus::PENDING_APPROVAL;
    }

    public static function hasManualProof(Payment $payment): bool
    {
        $metadata = self::metadata($payment);

        return filled($metadata['manual_payment_proof_path'] ?? null);
    }

    public static function manualProofPath(Payment $payment): ?string
    {
        $metadata = self::metadata($payment);

        $path = $metadata['manual_payment_proof_path'] ?? null;

        return filled($path) ? (string) $path : null;
    }

    public static function manualProofName(Payment $payment): string
    {
        $metadata = self::metadata($payment);

        return (string) ($metadata['manual_payment_proof_name'] ?? 'comprobante.pdf');
    }

    public static function approve(Payment $payment, int|string|null $approvedBy = null): bool
    {
        if (! self::isManualPendingApproval($payment)) {
            return false;
        }

        return DB::transaction(function () use ($payment, $approvedBy): bool {
            $metadata = self::metadata($payment);
            $metadata['manual_payment_approved_at'] = now()->toISOString();
            $metadata['manual_payment_approved_by'] = $approvedBy;

            $payment->update([
                'metadata' => $metadata,
            ]);

            $updated = $payment->markAsCompleted();

            if ($updated && $payment->plant_id) {
                app(PlantReservationService::class)->completeForPlant((int) $payment->plant_id);
            }

            if ($updated) {
                BusinessActivityLogger::logManualPaymentApproved($payment->fresh(), $approvedBy);
            }

            return $updated;
        });
    }

    public static function reject(Payment $payment, ?string $reason = null, int|string|null $rejectedBy = null): bool
    {
        if (! self::isManualPendingApproval($payment)) {
            return false;
        }

        return DB::transaction(function () use ($payment, $reason, $rejectedBy): bool {
            $metadata = self::metadata($payment);
            $metadata['manual_payment_rejected_at'] = now()->toISOString();
            $metadata['manual_payment_rejected_by'] = $rejectedBy;
            $metadata['manual_payment_rejection_reason'] = $reason;

            $payment->update([
                'metadata' => $metadata,
            ]);

            $updated = $payment->markAsFailed($reason ?? 'Pago manual rechazado por administracion');

            if ($updated && $payment->plant_id) {
                app(PlantReservationService::class)->releaseForPlant((int) $payment->plant_id, 'manual_payment_rejected');
            }

            if ($updated) {
                BusinessActivityLogger::logManualPaymentRejected($payment->fresh(), $reason, $rejectedBy);
            }

            return $updated;
        });
    }
}
