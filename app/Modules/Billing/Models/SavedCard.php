<?php

namespace App\Modules\Billing\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/** The card kept for automatic renewal: the gateway's ids plus brand, last four digits and expiry. */
class SavedCard extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id', 'restaurant_id'];

    protected $hidden = ['customer_id', 'method_id'];

    protected function casts(): array
    {
        return ['auto_renew' => 'boolean', 'last_attempt_at' => 'datetime'];
    }

    /** True while the card is still valid on the given day (cards run to the end of their expiry month). */
    public function validOn(\DateTimeInterface $day): bool
    {
        if (! $this->exp_year || ! $this->exp_month) {
            return true;
        }

        return $day <= CarbonImmutable::create((int) $this->exp_year, (int) $this->exp_month, 1)->endOfMonth();
    }
}
