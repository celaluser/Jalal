<?php

namespace App\Modules\Reservations\Events;

use App\Modules\Reservations\Models\Reservation;

/** A reservation was created (by a guest, by staff or through the API). */
class ReservationBooked
{
    public function __construct(public readonly Reservation $reservation) {}
}
