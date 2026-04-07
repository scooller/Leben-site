<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Plant;
use App\Models\PlantReservation;
use App\Models\SiteSetting;
use App\Support\BusinessActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlantReservationService
{
    private const DEFAULT_RESERVATION_DURATION_MINUTES = 15;

    /**
     * Reserve a plant for a user.
     * Uses DB transaction with pessimistic locking to prevent race conditions.
     *
     * @param  array<string, mixed>  $metadata
     *
     * @throws \InvalidArgumentException If plant does not exist or is not active
     * @throws \RuntimeException If plant is already reserved by another user
     */
    public function reserve(int $plantId, int $userId, array $metadata = []): PlantReservation
    {
        return DB::transaction(function () use ($plantId, $userId, $metadata) {
            $plant = Plant::lockForUpdate()->find($plantId);

            if (! $plant || ! $plant->is_active) {
                throw new \InvalidArgumentException('La planta no existe o no esta disponible.');
            }

            // Check for existing active reservation on this plant
            $existing = PlantReservation::where('plant_id', $plantId)
                ->where('status', ReservationStatus::ACTIVE)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($existing) {
                // If the same user already has the reservation, extend it
                if ($existing->user_id === $userId) {
                    $existing->update([
                        'expires_at' => now()->addMinutes($this->reservationDurationMinutes()),
                    ]);

                    Log::info('PlantReservation: Extended existing reservation', [
                        'reservation_id' => $existing->id,
                        'plant_id' => $plantId,
                        'user_id' => $userId,
                    ]);

                    return $existing->fresh();
                }

                throw new \RuntimeException('Esta planta ya esta reservada por otro usuario.');
            }

            // Expire any stale active reservations for this plant
            PlantReservation::where('plant_id', $plantId)
                ->where('status', ReservationStatus::ACTIVE)
                ->where('expires_at', '<=', now())
                ->update([
                    'status' => ReservationStatus::EXPIRED,
                    'released_at' => now(),
                    'released_by' => 'system',
                ]);

            $reservation = PlantReservation::create([
                'plant_id' => $plantId,
                'user_id' => $userId,
                'session_token' => Str::uuid()->toString(),
                'status' => ReservationStatus::ACTIVE,
                'expires_at' => now()->addMinutes($this->reservationDurationMinutes()),
                'metadata' => $metadata,
            ]);

            Log::info('PlantReservation: Created new reservation', [
                'reservation_id' => $reservation->id,
                'plant_id' => $plantId,
                'user_id' => $userId,
                'expires_at' => $reservation->expires_at->toISOString(),
            ]);

            return $reservation;
        });
    }

    /**
     * Release a reservation by session token.
     */
    public function releaseByToken(string $sessionToken, string $releasedBy = 'user', ?string $reason = null): bool
    {
        $reservation = PlantReservation::where('session_token', $sessionToken)
            ->where('status', ReservationStatus::ACTIVE)
            ->first();

        if (! $reservation) {
            return false;
        }

        $reservation->release($releasedBy, $reason);

        BusinessActivityLogger::logReservationReleased($reservation->fresh(), $releasedBy, $reason, 'release_by_token');

        Log::info('PlantReservation: Released by token', [
            'reservation_id' => $reservation->id,
            'plant_id' => $reservation->plant_id,
            'released_by' => $releasedBy,
        ]);

        return true;
    }

    /**
     * Release a reservation by ID (for admin Filament action).
     */
    public function releaseById(int $reservationId, string $releasedBy = 'admin', ?string $reason = null): bool
    {
        $reservation = PlantReservation::where('id', $reservationId)
            ->where('status', ReservationStatus::ACTIVE)
            ->first();

        if (! $reservation) {
            return false;
        }

        $reservation->release($releasedBy, $reason);

        BusinessActivityLogger::logReservationReleased($reservation->fresh(), $releasedBy, $reason, 'release_by_id');

        Log::info('PlantReservation: Released by ID', [
            'reservation_id' => $reservationId,
            'released_by' => $releasedBy,
        ]);

        return true;
    }

    /**
     * Complete a reservation (called when payment is confirmed).
     */
    public function completeForPlant(int $plantId): bool
    {
        $reservation = PlantReservation::where('plant_id', $plantId)
            ->where('status', ReservationStatus::ACTIVE)
            ->first();

        if (! $reservation) {
            Log::warning('PlantReservation: No active reservation found to complete', [
                'plant_id' => $plantId,
            ]);

            return false;
        }

        $reservation->markAsCompleted();

        BusinessActivityLogger::logReservationCompleted($reservation->fresh(), 'complete_for_plant');

        Log::info('PlantReservation: Completed', [
            'reservation_id' => $reservation->id,
            'plant_id' => $plantId,
        ]);

        return true;
    }

    /**
     * Release reservation when payment fails.
     */
    public function releaseForPlant(int $plantId, string $reason = 'payment_failed'): bool
    {
        $reservation = PlantReservation::where('plant_id', $plantId)
            ->where('status', ReservationStatus::ACTIVE)
            ->first();

        if (! $reservation) {
            return false;
        }

        $reservation->release('system', $reason);

        BusinessActivityLogger::logReservationReleased($reservation->fresh(), 'system', $reason, 'release_for_plant');

        Log::info('PlantReservation: Released due to payment failure', [
            'reservation_id' => $reservation->id,
            'plant_id' => $plantId,
        ]);

        return true;
    }

    /**
     * Check reservation status for a specific plant.
     */
    public function checkPlantStatus(int $plantId): ?PlantReservation
    {
        return PlantReservation::where('plant_id', $plantId)
            ->where('status', ReservationStatus::ACTIVE)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Validate that a given session token owns the active reservation for a plant.
     *
     * @throws \RuntimeException If no valid reservation exists
     */
    public function validateReservationForCheckout(int $plantId, string $sessionToken): PlantReservation
    {
        $reservation = PlantReservation::where('plant_id', $plantId)
            ->where('session_token', $sessionToken)
            ->where('status', ReservationStatus::ACTIVE)
            ->where('expires_at', '>', now())
            ->first();

        if (! $reservation) {
            throw new \RuntimeException('No tienes una reserva activa para esta planta. Por favor, intenta de nuevo.');
        }

        return $reservation;
    }

    /**
     * Extend an active reservation for a manual payment deadline.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function extendForManualPayment(PlantReservation $reservation, \DateTimeInterface $expiresAt, array $metadata = []): PlantReservation
    {
        $reservationMetadata = array_merge($reservation->metadata ?? [], $metadata);

        $reservation->update([
            'expires_at' => $expiresAt,
            'metadata' => $reservationMetadata,
        ]);

        Log::info('PlantReservation: Extended for manual payment', [
            'reservation_id' => $reservation->id,
            'plant_id' => $reservation->plant_id,
            'expires_at' => $reservation->fresh()->expires_at?->toISOString(),
        ]);

        return $reservation->fresh();
    }

    /**
     * Expire all stale reservations.
     *
     * @return int Number of reservations expired
     */
    public function expireStaleReservations(): int
    {
        $count = PlantReservation::where('status', ReservationStatus::ACTIVE)
            ->where('expires_at', '<', now())
            ->update([
                'status' => ReservationStatus::EXPIRED,
                'released_at' => now(),
                'released_by' => 'system',
            ]);

        if ($count > 0) {
            Log::info('PlantReservation: Expired stale reservations', ['count' => $count]);
        }

        return $count;
    }

    private function reservationDurationMinutes(): int
    {
        $minutes = SiteSetting::get('gateway_reservation_timeout_minutes', self::DEFAULT_RESERVATION_DURATION_MINUTES);

        return (int) max(1, min(120, (int) $minutes));
    }
}
