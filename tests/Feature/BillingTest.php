<?php

use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\CouponService;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Tenancy\Models\Restaurant;

function plan(array $overrides = []): Plan
{
    return Plan::create(array_merge([
        'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'interval' => 'monthly', 'price' => 29.99, 'currency_code' => 'USD',
        'trial_days' => 14, 'limits' => ['products' => 50, 'tables' => null], 'features' => ['custom_domain' => true],
    ], $overrides));
}

function resto(string $slug = 'r'): Restaurant
{
    return Restaurant::create(['name' => ucfirst($slug), 'slug' => $slug.uniqid()]);
}

describe('subscriptions', function () {
    beforeEach(function () {
        $this->subs = app(SubscriptionService::class);
        $this->r = resto();
    });

    it('starts one trial per plan that ends after the trial days', function () {
        $plan = plan();
        $sub = $this->subs->startTrial($this->r, $plan);

        expect($sub->status)->toBe('trialing')
            ->and($sub->trial_ends_at->isSameDay(now()->addDays(14)))->toBeTrue()
            ->and($sub->grantsAccess())->toBeTrue();

        expect(fn () => $this->subs->startTrial($this->r, $plan))->toThrow(BillingException::class);
    });

    it('keeps exactly one current subscription when the plan changes', function () {
        $this->subs->assign($this->r, plan(['slug' => 'a']));
        $second = $this->subs->assign($this->r, plan(['slug' => 'b']));

        expect($this->subs->current($this->r)->id)->toBe($second->id)
            ->and(Subscription::allTenants()->where('restaurant_id', $this->r->id)->where('status', 'active')->count())->toBe(1);
    });

    it('sets the end date from the plan interval and never ends lifetime or free plans', function () {
        $monthly = $this->subs->assign($this->r, plan(['interval' => 'monthly']));
        expect($monthly->ends_at->isSameDay(now()->addMonth()))->toBeTrue();

        $yearly = $this->subs->assign($this->r, plan(['interval' => 'yearly']));
        expect($yearly->ends_at->isSameDay(now()->addYear()))->toBeTrue();

        expect($this->subs->assign($this->r, plan(['interval' => 'lifetime']))->ends_at)->toBeNull()
            ->and($this->subs->assign($this->r, plan(['interval' => 'free', 'price' => 0]))->ends_at)->toBeNull();
    });

    it('renews from the old end date while it is still in the future', function () {
        $sub = $this->subs->assign($this->r, plan());
        $oldEnd = $sub->ends_at->copy();

        expect($this->subs->renew($sub)->ends_at->isSameDay($oldEnd->copy()->addMonth()))->toBeTrue();
    });

    it('keeps access until the period ends after a soft cancel, but not after an immediate cancel', function () {
        $sub = $this->subs->cancel($this->subs->assign($this->r, plan()));
        expect($sub->canceled_at)->not->toBeNull()->and($this->subs->current($this->r))->not->toBeNull();

        $this->subs->cancel($sub, immediately: true);
        expect($this->subs->current($this->r))->toBeNull();
    });

    it('expires trials, gives unpaid subscriptions a grace period, then expires them', function () {
        config(['billing.grace_days' => 3]);
        $plan = plan();
        $trial = $this->subs->startTrial(resto('t'), $plan);
        $trial->update(['ends_at' => now()->subHour()]);

        $late = $this->subs->assign(resto('l'), $plan, now()->subDay());
        $dead = $this->subs->assign(resto('d'), $plan, now()->subDays(10));
        $canceled = $this->subs->cancel($this->subs->assign(resto('c'), $plan, now()->subHour()));

        $this->subs->expireDue();

        expect($trial->fresh()->status)->toBe('expired')
            ->and($late->fresh()->status)->toBe('past_due')
            ->and($dead->fresh()->status)->toBe('expired')
            ->and($canceled->fresh()->status)->toBe('expired');
    });

    it('does not let a restaurant see another restaurant\'s subscriptions through the tenant scope', function () {
        $other = resto('other');
        $this->subs->assign($this->r, plan());
        $this->subs->assign($other, plan());

        $ids = app(TenantContext::class)->runAs($this->r, fn () => Subscription::pluck('restaurant_id')->all());

        expect($ids)->toBe([$this->r->id]);
    });
});

describe('plan limits', function () {
    it('answers limit and feature questions from the current plan', function () {
        $guard = app(LimitGuard::class);
        $r = resto();

        // No subscription: nothing limited is allowed.
        expect($guard->canAdd($r, 'products', 0))->toBeFalse()->and($guard->hasFeature($r, 'custom_domain'))->toBeFalse();

        app(SubscriptionService::class)->assign($r, plan());

        expect($guard->canAdd($r, 'products', 49))->toBeTrue()
            ->and($guard->canAdd($r, 'products', 50))->toBeFalse()
            ->and($guard->canAdd($r, 'products', 40, adding: 11))->toBeFalse()
            ->and($guard->remaining($r, 'products', 45))->toBe(5)
            ->and($guard->canAdd($r, 'tables', 100000))->toBeTrue() // null = unlimited
            ->and($guard->remaining($r, 'tables', 5))->toBeNull()
            ->and($guard->hasFeature($r, 'custom_domain'))->toBeTrue()
            ->and($guard->hasFeature($r, 'reservations'))->toBeFalse();
    });
});

describe('coupons', function () {
    it('computes percent and fixed discounts and never exceeds the subtotal', function () {
        $percent = new Coupon(['type' => 'percent', 'value' => 25]);
        $fixed = new Coupon(['type' => 'fixed', 'value' => 500]);

        expect($percent->discountFor(80.0))->toBe(20.0)
            ->and($fixed->discountFor(80.0))->toBe(80.0);
    });

    it('rejects inactive, expired, not started, used up and wrong-plan coupons', function (array $attributes, string $reason) {
        $plan = plan();
        Coupon::create(array_merge(['code' => 'X1', 'type' => 'percent', 'value' => 10], $attributes));

        expect(fn () => app(CouponService::class)->validate('x1', $plan))->toThrow(BillingException::class, __($reason));
    })->with([
        'inactive' => [['is_active' => false], 'coupon.inactive'],
        'expired' => [['expires_at' => now()->subDay()], 'coupon.expired'],
        'not started' => [['starts_at' => now()->addDay()], 'coupon.not_started'],
        'used up' => [['max_uses' => 1, 'used_count' => 1], 'coupon.used_up'],
        'wrong plan' => [['plan_ids' => [99999]], 'coupon.wrong_plan'],
    ]);

    it('is case-insensitive on lookup and unknown codes are rejected', function () {
        Coupon::create(['code' => 'save10', 'type' => 'percent', 'value' => 10]);

        expect(app(CouponService::class)->validate(' SAVE10 ', plan())->code)->toBe('SAVE10');
        expect(fn () => app(CouponService::class)->validate('nope', plan()))->toThrow(BillingException::class);
    });
});

describe('invoices', function () {
    beforeEach(function () {
        $this->invoices = app(InvoiceService::class);
        $this->settings = app(SettingsService::class);
    });

    it('adds tax on top of the net price', function () {
        $this->settings->set('billing.tax_rate', '18');

        expect($this->invoices->calculate(100))->toMatchArray(['subtotal' => 10000, 'discount' => 0, 'tax' => 1800, 'total' => 11800]);
    });

    it('extracts tax from a tax-inclusive price', function () {
        $this->settings->set('billing.tax_rate', '20');
        $this->settings->set('billing.prices_include_tax', '1');

        expect($this->invoices->calculate(120))->toMatchArray(['subtotal' => 12000, 'tax' => 2000, 'total' => 12000]);
    });

    it('applies the discount before tax', function () {
        $this->settings->set('billing.tax_rate', '10');
        $coupon = new Coupon(['type' => 'percent', 'value' => 50]);

        expect($this->invoices->calculate(100, $coupon))->toMatchArray(['discount' => 5000, 'tax' => 500, 'total' => 5500]);
    });

    it('does not drift on awkward cents', function () {
        $this->settings->set('billing.tax_rate', '7.5');

        $a = $this->invoices->calculate(19.99);
        expect($a['subtotal'])->toBe(1999)->and($a['tax'])->toBe(150)->and($a['total'])->toBe(2149);
    });

    it('creates sequential numbers, snapshots buyer and seller and marks free invoices paid', function () {
        $this->settings->set('billing.company_name', 'Acme SaaS Ltd');
        $r = resto('buyer');
        $paid = plan(['price' => 10]);
        $free = plan(['slug' => 'free', 'interval' => 'free', 'price' => 0]);

        $one = $this->invoices->create($r, $paid);
        $two = $this->invoices->create($r, $paid);
        $zero = $this->invoices->create($r, $free);

        expect($one->number)->toBe('INV-'.now()->year.'-000001')
            ->and($two->number)->toBe('INV-'.now()->year.'-000002')
            ->and($one->status)->toBe('open')
            ->and($zero->status)->toBe('paid')
            ->and($one->billing['seller']['name'])->toBe('Acme SaaS Ltd')
            ->and($one->billing['buyer']['name'])->toBe($r->name);
    });

    it('redeems a coupon exactly once when the invoice is paid, even if marked paid twice', function () {
        $coupon = Coupon::create(['code' => 'ONCE', 'type' => 'fixed', 'value' => 5, 'max_uses' => 1]);
        $invoice = $this->invoices->create(resto(), plan(['price' => 10]), coupon: $coupon);

        $this->invoices->markPaid($invoice, 'manual', 'ref-1');
        $this->invoices->markPaid($invoice->fresh(), 'manual', 'ref-1');

        expect($coupon->fresh()->used_count)->toBe(1)->and($invoice->fresh()->status)->toBe('paid');
    });

    it('refuses to redeem a coupon that was used up in the meantime', function () {
        $coupon = Coupon::create(['code' => 'RACE', 'type' => 'fixed', 'value' => 5, 'max_uses' => 1]);
        $a = $this->invoices->create(resto('a'), plan(['price' => 10]), coupon: $coupon);
        $b = $this->invoices->create(resto('b'), plan(['price' => 10]), coupon: $coupon);

        $this->invoices->markPaid($a);

        expect(fn () => $this->invoices->markPaid($b))->toThrow(BillingException::class)
            ->and($coupon->fresh()->used_count)->toBe(1);
    });

    it('renders a PDF', function () {
        $invoice = $this->invoices->create(resto(), plan(['price' => 10]));

        expect(substr($this->invoices->pdf($invoice)->output(), 0, 4))->toBe('%PDF');
    });
});
