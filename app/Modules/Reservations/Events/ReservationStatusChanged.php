<?php

namespace App\Modules\Reservations\Events;

use App\Modules\Reservations\Models\Reservation;

class ReservationStatusChanged
{
    public function __construct(public readonly Reservation $reservation, public readonly string $from, public readonly string $to) {}
}
