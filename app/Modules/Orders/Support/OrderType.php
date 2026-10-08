<?php

namespace App\Modules\Orders\Support;

final class OrderType
{
    public const DINE_IN = 'dine_in';

    public const TAKEAWAY = 'takeaway';

    public const DELIVERY = 'delivery';

    /** Pick-up from the car park: the guest stays in the vehicle and the food is brought out. */
    public const CURBSIDE = 'curbside';

    /** Hotel guests ordering to their room. */
    public const ROOM_SERVICE = 'room_service';

    /** @var list<string> */
    public const ALL = [self::DINE_IN, self::TAKEAWAY, self::DELIVERY, self::CURBSIDE, self::ROOM_SERVICE];

    /** Types where the guest is not at a table, so the kitchen needs a phone number to reach them. */
    public const NEEDS_PHONE = [self::TAKEAWAY, self::DELIVERY, self::CURBSIDE];

    /** Types that leave in a box: the packaging fee applies. */
    public const PACKED = [self::TAKEAWAY, self::DELIVERY, self::CURBSIDE];
}
