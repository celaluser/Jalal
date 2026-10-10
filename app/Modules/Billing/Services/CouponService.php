<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\CouponRedemption;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use Illuminate\Support\Facades\DB;

class CouponService
{
    /**
     * @throws BillingException with a translated reason
     */
    public function validate(string $code, Plan $plan): Coupon
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        if (! $coupon) {
            throw new BillingException(__('coupon.not_found'));
        }

        if ($reason = $coupon->rejectionFor($plan)) {
            throw new BillingException(__($reason));
        }

        return $coupon;
    }

    /**
     * Count a use, atomically: the row is only updated while uses remain, so two parallel
     * checkouts cannot both take the last use.
     */
    public function redeem(Coupon $coupon, Invoice $invoice): void
    {
        DB::transaction(function () use ($coupon, $invoice) {
            $updated = Coupon::where('id', $coupon->id)
                ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'))
                ->increment('used_count');

            if ($updated === 0) {
                throw new BillingException(__('coupon.used_up'));
            }

            CouponRedemption::create([
                'coupon_id' => $coupon->id,
                'restaurant_id' => $invoice->restaurant_id,
                'invoice_id' => $invoice->id,
                'amount' => $invoice->discount_amount,
            ]);
        });
    }
}
