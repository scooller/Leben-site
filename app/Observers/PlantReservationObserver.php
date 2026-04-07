<?php

namespace App\Observers;

use App\Enums\ReservationStatus;
use App\Models\PlantReservation;
use App\Services\FinMail\FinMailNotificationService;

class PlantReservationObserver
{
    public function created(PlantReservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::ACTIVE) {
            return;
        }

        app(FinMailNotificationService::class)->sendUnitReservationCreated($reservation);
    }
}
