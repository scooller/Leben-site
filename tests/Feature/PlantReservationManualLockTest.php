<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Plant;
use App\Models\PlantReservation;
use App\Models\User;
use App\Services\PlantReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlantReservationManualLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_lock_reservation_cannot_be_released_by_any_release_flow(): void
    {
        $user = User::factory()->create();
        $plant = Plant::factory()->create([
            'is_active' => true,
        ]);

        $reservation = PlantReservation::query()->create([
            'plant_id' => $plant->id,
            'user_id' => $user->id,
            'session_token' => Str::uuid()->toString(),
            'status' => ReservationStatus::ACTIVE,
            'expires_at' => now()->addDay(),
            'metadata' => [
                'manual_lock' => true,
            ],
        ]);

        $service = app(PlantReservationService::class);

        $this->assertFalse($service->releaseById($reservation->id, 'admin', 'test'));
        $this->assertFalse($service->releaseByToken($reservation->session_token, 'user', 'test'));
        $this->assertFalse($service->releaseForPlant($plant->id, 'payment_failed'));

        $reservation->refresh();

        $this->assertSame(ReservationStatus::ACTIVE, $reservation->status);
        $this->assertNull($reservation->released_at);
        $this->assertNull($reservation->released_by);
    }

    public function test_expire_stale_reservations_skips_manual_lock_records(): void
    {
        $user = User::factory()->create();

        $manualPlant = Plant::factory()->create(['is_active' => true]);
        $normalPlant = Plant::factory()->create(['is_active' => true]);

        $manualReservation = PlantReservation::query()->create([
            'plant_id' => $manualPlant->id,
            'user_id' => $user->id,
            'session_token' => Str::uuid()->toString(),
            'status' => ReservationStatus::ACTIVE,
            'expires_at' => now()->subMinute(),
            'metadata' => [
                'manual_lock' => true,
            ],
        ]);

        $normalReservation = PlantReservation::query()->create([
            'plant_id' => $normalPlant->id,
            'user_id' => $user->id,
            'session_token' => Str::uuid()->toString(),
            'status' => ReservationStatus::ACTIVE,
            'expires_at' => now()->subMinute(),
            'metadata' => [],
        ]);

        $expiredCount = app(PlantReservationService::class)->expireStaleReservations();

        $this->assertSame(1, $expiredCount);

        $manualReservation->refresh();
        $normalReservation->refresh();

        $this->assertSame(ReservationStatus::ACTIVE, $manualReservation->status);
        $this->assertSame(ReservationStatus::EXPIRED, $normalReservation->status);
    }
}
