<?php

namespace App\Modules\Branches\Models;

use App\Modules\Activity\Support\LogsActivity;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use App\Modules\Menu\Support\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/** One location of a restaurant: its own tables, staff, opening hours and, where it differs, prices and stock. */
class Branch extends Model
{
    use BelongsToRestaurant, LogsActivity;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['schedule' => 'array', 'is_active' => 'boolean'];
    }

    public function isOpen(?CarbonImmutable $now = null): bool
    {
        $tz = in_array($this->restaurant?->timezone, timezone_identifiers_list(), true) ? $this->restaurant->timezone : 'UTC';

        return $this->is_active && Schedule::isOpen($this->schedule, $now ?? CarbonImmutable::now($tz));
    }
}
