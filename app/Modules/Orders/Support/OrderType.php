<?php

namespace App\Modules\Orders\Support;

final class OrderType
{
    public const DINE_IN = 'dine_in';

    public const TAKEAWAY = 'takeaway';

    public const DELIVERY = 'delivery';

    /** @var list<string> */
    public const ALL = [self::DINE_IN, self::TAKEAWAY, self::DELIVERY];
}
