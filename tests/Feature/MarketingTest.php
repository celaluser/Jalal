<?php

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Mail\CampaignMail;
use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Models\CampaignRecipient;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Models\PromoCode;
use App\Modules\Marketing\Models\Review;
use App\Modules\Marketing\Services\CampaignService;
use App\Modules\Marketing\Services\CustomerService;
use App\Modules\Marketing\Services\LoyaltyService;
use App\Modules\Marketing\Services\MarketingSettings;
use App\Modules\Marketing\Services\PromoService;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Mail::fake();
});

/** @return array{0: Restaurant, 1: array<string, mixed>} a restaurant with a $10 pizza, a table, and ordering settings */
function mkShop(array $order = [], array $marketing = []): array
{
    $r = Restaurant::create([
        'name' => 'Promo Place', 'slug' => 'pp'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'onboarded_at' => now(),
        'order_settings' => $order, 'marketing_settings' => $marketing,
    ]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);

        return ['pizza' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Pizza'], 'price' => 10, 'sort' => 1]), 'table' => DiningTable::create(['name' => 'T1'])];
    });

    return [$r, $d];
}

function mkUser(Restaurant $r, string $role): User
{
    $user = User::factory()->create(['restaurant_id' => $r->id]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $user;
}

function mkIn(Restaurant $r, Closure $fn): mixed
{
    return app(TenantContext::class)->runAs($r, $fn);
}

function mkOrder(Restaurant $r, array $d, array $over = [], int $qty = 1): Order
{
    return mkIn($r, fn () => app(OrderService::class)->place($r, array_merge([
        'type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'Gus Guest', 'customer_phone' => '+1 555 111 2222',
        'lines' => [['product_id' => $d['pizza']->id, 'qty' => $qty]],
    ], $over)));
}

function mkMove(Restaurant $r, Order $o, string ...$steps): void
{
    foreach ($steps as $to) {
        mkIn($r, fn () => app(OrderService::class)->transition(Order::find($o->id), $to));
    }
}

function mkComplete(Restaurant $r, Order $o): void
{
    mkMove($r, $o, OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY, OrderStatus::COMPLETED);
}

function mkCustomer(Restaurant $r, array $attrs = []): Customer
{
    return mkIn($r, fn () => Customer::create(array_merge(['name' => 'Cu '.uniqid(), 'email' => uniqid().'@example.com', 'marketing_opt_in' => true, 'opted_in_at' => now(), 'orders_count' => 1], $attrs)));
}

describe('customer records', function () {
    it('creates one customer per person and keeps their totals', function () {
        [$r, $d] = mkShop();
        $o1 = mkOrder($r, $d, ['customer_email' => 'Gus@Example.com']);
        mkOrder($r, $d, ['customer_email' => 'gus@example.com', 'customer_phone' => '0555 111 2222'], 2);

        $customers = mkIn($r, fn () => Customer::all());
        expect($customers)->toHaveCount(1)->and($customers[0]->email)->toBe('gus@example.com')->and($customers[0]->orders_count)->toBe(2)->and($customers[0]->total_cents)->toBe(3000)
            ->and(mkIn($r, fn () => Order::find($o1->id)->customer_id))->toBe($customers[0]->id);
    });

    it('recognises the same phone number written differently', function () {
        [$r, $d] = mkShop();
        mkOrder($r, $d, ['customer_phone' => '+90 (532) 111-22-33']);
        mkOrder($r, $d, ['customer_phone' => '05321112233', 'customer_email' => 'new@example.com']);

        $c = mkIn($r, fn () => Customer::all());
        expect($c)->toHaveCount(1)->and($c[0]->email)->toBe('new@example.com');
    });

    it('keeps no record of a guest who left nothing', function () {
        [$r, $d] = mkShop();
        mkOrder($r, $d, ['type' => 'dine_in', 'table_id' => $d['table']->id, 'customer_name' => null, 'customer_phone' => null]);
        expect(mkIn($r, fn () => Customer::count()))->toBe(0);
    });

    it('does not count cancelled orders', function () {
        [$r, $d] = mkShop();
        $o = mkOrder($r, $d, ['customer_email' => 'a@example.com']);
        mkMove($r, $o, OrderStatus::CANCELLED);
        $c = mkIn($r, fn () => Customer::first());
        expect($c->orders_count)->toBe(0)->and($c->total_cents)->toBe(0);
    });

    it('only records marketing consent when the guest ticked the box, and needs an address', function () {
        [$r, $d] = mkShop();
        mkOrder($r, $d, ['customer_email' => 'no@example.com', 'customer_phone' => '+1 555 100 0001']);
        mkOrder($r, $d, ['customer_email' => 'yes@example.com', 'customer_phone' => '+1 555 100 0002', 'marketing_opt_in' => true]);
        mkOrder($r, $d, ['customer_phone' => '+1 555 999 8888', 'customer_email' => null, 'marketing_opt_in' => true]);

        $by = mkIn($r, fn () => Customer::all()->keyBy(fn ($c) => $c->email ?: 'phone'));
        expect($by['no@example.com']->marketing_opt_in)->toBeFalse()->and($by['yes@example.com']->marketing_opt_in)->toBeTrue()->and($by['yes@example.com']->canBeEmailed())->toBeTrue()
            ->and($by['phone']->marketing_opt_in)->toBeFalse();
    });

    it('treats a new yes after unsubscribing as fresh consent', function () {
        [$r, $d] = mkShop();
        mkOrder($r, $d, ['customer_email' => 'a@example.com', 'marketing_opt_in' => true]);
        mkIn($r, fn () => app(CustomerService::class)->unsubscribe(Customer::first()));
        expect(mkIn($r, fn () => Customer::first()->canBeEmailed()))->toBeFalse();

        mkOrder($r, $d, ['customer_email' => 'a@example.com']); // no tick: stays unsubscribed
        expect(mkIn($r, fn () => Customer::first()->canBeEmailed()))->toBeFalse();
        mkOrder($r, $d, ['customer_email' => 'a@example.com', 'marketing_opt_in' => true]);
        expect(mkIn($r, fn () => Customer::first()->canBeEmailed()))->toBeTrue();
    });
});

describe('customer screens', function () {
    it('lists, searches and filters only this restaurant’s customers', function () {
        [$r, $d] = mkShop();
        [$other, $od] = mkShop();
        mkOrder($r, $d, ['customer_name' => 'Ada Lovelace', 'customer_email' => 'ada@example.com', 'marketing_opt_in' => true]);
        mkOrder($r, $d, ['customer_name' => 'Bob Builder', 'customer_phone' => '+1 555 000 1111']);
        mkOrder($other, $od, ['customer_name' => 'Zed Stranger', 'customer_email' => 'zed@example.com']);
        $owner = mkUser($r, 'manager');

        $this->actingAs($owner)->get(route('customers.index'))->assertOk()->assertSee('Ada Lovelace')->assertSee('Bob Builder')->assertDontSee('Zed Stranger');
        $this->actingAs($owner)->get(route('customers.index', ['q' => 'ada']))->assertSee('Ada Lovelace')->assertDontSee('Bob Builder');
        $this->actingAs($owner)->get(route('customers.index', ['filter' => 'opted']))->assertSee('Ada Lovelace')->assertDontSee('Bob Builder');
    });

    it('shows a customer with order history and keeps others out', function () {
        [$r, $d] = mkShop();
        [$other, $od] = mkShop();
        mkOrder($r, $d, ['customer_email' => 'ada@example.com']);
        mkOrder($other, $od, ['customer_email' => 'zed@example.com']);
        $mine = mkIn($r, fn () => Customer::first());
        $theirs = mkIn($other, fn () => Customer::first());
        $manager = mkUser($r, 'manager');

        $this->actingAs($manager)->get(route('customers.show', $mine->id))->assertOk()->assertSee('ada@example.com')->assertSee('#1001');
        $this->actingAs($manager)->get(route('customers.show', $theirs->id))->assertNotFound();
        $this->actingAs($manager)->put(route('customers.update', $theirs->id), ['notes' => 'x'])->assertNotFound();
        $this->actingAs($manager)->delete(route('customers.destroy', $theirs->id))->assertNotFound();
    });

    it('saves private notes', function () {
        [$r, $d] = mkShop();
        mkOrder($r, $d, ['customer_email' => 'ada@example.com']);
        $c = mkIn($r, fn () => Customer::first());
        $this->actingAs(mkUser($r, 'manager'))->put(route('customers.update', $c->id), ['notes' => 'Allergic to nuts'])->assertRedirect();
        expect(mkIn($r, fn () => Customer::first()->notes))->toBe('Allergic to nuts');
    });

    it('erases a customer and their personal details but keeps the sale', function () {
        [$r, $d] = mkShop(['delivery' => true]);
        $o = mkOrder($r, $d, ['customer_email' => 'ada@example.com', 'type' => 'delivery', 'delivery_address' => '12 Long Street']);
        $c = mkIn($r, fn () => Customer::first());
        $this->actingAs(mkUser($r, 'manager'))->delete(route('customers.destroy', $c->id))->assertRedirect(route('customers.index'));

        $order = mkIn($r, fn () => Order::find($o->id));
        expect(mkIn($r, fn () => Customer::count()))->toBe(0)->and($order->customer_email)->toBeNull()->and($order->customer_phone)->toBeNull()->and($order->customer_name)->toBeNull()
            ->and($order->delivery_address)->toBeNull()->and($order->total_cents)->toBe(1000);
    });

    it('exports a CSV that cannot run formulas in a spreadsheet', function () {
        [$r, $d] = mkShop();
        [$other, $od] = mkShop();
        mkOrder($r, $d, ['customer_name' => '=HYPERLINK("http://evil")', 'customer_email' => 'ada@example.com']);
        mkOrder($other, $od, ['customer_email' => 'zed@example.com']);

        $res = $this->actingAs(mkUser($r, 'manager'))->get(route('customers.export'))->assertOk();
        $csv = $res->streamedContent();
        expect($csv)->toContain('ada@example.com')->not->toContain('zed@example.com')->toContain("'=HYPERLINK")->and($csv)->not->toContain(',=HYPERLINK');
    });

    it('is closed to waiters and kitchen', function () {
        [$r] = mkShop();
        foreach (['waiter', 'kitchen'] as $role) {
            $u = mkUser($r, $role);
            $this->actingAs($u)->get(route('customers.index'))->assertForbidden();
            $this->actingAs($u)->get(route('promos.index'))->assertForbidden();
            $this->actingAs($u)->get(route('campaigns.index'))->assertForbidden();
            $this->actingAs($u)->get(route('reviews.index'))->assertForbidden();
        }
    });
});

describe('promo codes', function () {
    function mkPromo(Restaurant $r, array $attrs = []): PromoCode
    {
        return mkIn($r, fn () => PromoCode::create(array_merge(['code' => 'WELCOME10', 'type' => 'percent', 'value' => 10], $attrs)));
    }

    it('takes a percentage off the items before service and tax, and counts the use', function () {
        [$r, $d] = mkShop(['service_rate' => '10', 'tax_rate' => '0']);
        $promo = mkPromo($r);
        $o = mkOrder($r, $d, ['type' => 'dine_in', 'table_id' => $d['table']->id, 'promo_code' => ' welcome10 '], 2);

        expect($o->subtotal_cents)->toBe(2000)->and($o->discount_cents)->toBe(200)->and($o->service_cents)->toBe(180)->and($o->total_cents)->toBe(1980)->and($o->promo_code)->toBe('WELCOME10')
            ->and(mkIn($r, fn () => $promo->fresh()->uses_count))->toBe(1);
    });

    it('takes a fixed amount off but never more than the items cost', function () {
        [$r, $d] = mkShop();
        mkPromo($r, ['code' => 'FIVE', 'type' => 'fixed', 'value' => 500]);
        mkPromo($r, ['code' => 'HUGE', 'type' => 'fixed', 'value' => 99999]);

        expect(mkOrder($r, $d, ['promo_code' => 'FIVE'])->total_cents)->toBe(500)->and(mkOrder($r, $d, ['promo_code' => 'HUGE'])->total_cents)->toBe(0);
    });

    it('rejects unknown, switched-off, expired, not-yet-started, used-up and too-small codes', function () {
        [$r, $d] = mkShop();
        mkPromo($r, ['code' => 'OFF', 'is_active' => false]);
        mkPromo($r, ['code' => 'OLD', 'ends_at' => now()->subDay()]);
        mkPromo($r, ['code' => 'SOON', 'starts_at' => now()->addDay()]);
        mkPromo($r, ['code' => 'ONCE', 'max_uses' => 1]);
        mkPromo($r, ['code' => 'BIG', 'min_order_cents' => 5000]);
        $reason = function (string $code) use ($r, $d) {
            try {
                mkOrder($r, $d, ['promo_code' => $code]);
            } catch (OrderException $e) {
                return $e->reason;
            }

            return 'accepted';
        };

        expect($reason('NOPE'))->toBe('promo_invalid')->and($reason('OFF'))->toBe('promo_invalid')->and($reason('OLD'))->toBe('promo_expired')->and($reason('SOON'))->toBe('promo_expired')
            ->and($reason('BIG'))->toBe('promo_min')->and($reason('ONCE'))->toBe('accepted')->and($reason('ONCE'))->toBe('promo_used');
        expect(mkIn($r, fn () => Order::count()))->toBe(1);
    });

    it('does not let the last use be taken twice', function () {
        [$r] = mkShop();
        $promo = mkPromo($r, ['max_uses' => 1]);
        $service = app(PromoService::class);
        expect(mkIn($r, fn () => $service->redeem($promo)))->toBeTrue()->and(mkIn($r, fn () => $service->redeem($promo)))->toBeFalse();
    });

    it('never applies another restaurant’s code', function () {
        [$r, $d] = mkShop();
        [$other] = mkShop();
        mkPromo($other);
        expect(fn () => mkOrder($r, $d, ['promo_code' => 'WELCOME10']))->toThrow(OrderException::class);
    });

    it('previews the discount in the checkout quote', function () {
        [$r, $d] = mkShop();
        mkPromo($r);
        $payload = ['type' => 'takeaway', 'lines' => [['product_id' => $d['pizza']->id, 'qty' => 2]]];

        $ok = $this->postJson("/r/{$r->slug}/cart/quote", $payload + ['promo_code' => 'welcome10'])->assertOk();
        expect($ok->json('promo.valid'))->toBeTrue()->and($ok->json('totals.raw.discount'))->toBe(200)->and($ok->json('totals.raw.total'))->toBe(1800);

        $bad = $this->postJson("/r/{$r->slug}/cart/quote", $payload + ['promo_code' => 'NOPE'])->assertOk();
        expect($bad->json('promo.valid'))->toBeFalse()->and($bad->json('totals.raw.total'))->toBe(2000);
    });

    it('places a discounted order through the guest checkout', function () {
        [$r, $d] = mkShop();
        mkPromo($r);
        $this->postJson("/r/{$r->slug}/order", [
            'type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'customer_email' => 'g@example.com', 'marketing_opt_in' => true,
            'promo_code' => 'WELCOME10', 'lines' => [['product_id' => $d['pizza']->id, 'qty' => 1]],
        ])->assertCreated();

        $order = mkIn($r, fn () => Order::first());
        expect($order->discount_cents)->toBe(100)->and($order->marketing_opt_in)->toBeTrue();
    });

    it('manages codes from the panel', function () {
        [$r] = mkShop();
        $owner = mkUser($r, 'manager');
        $this->actingAs($owner)->post(route('promos.store'), ['code' => 'summer 20!', 'type' => 'percent', 'value' => 20, 'min_order' => '15', 'max_uses' => 50, 'is_active' => '1', 'ends_at' => now()->addDays(10)->toDateString()])->assertRedirect(route('promos.index'));
        $this->actingAs($owner)->post(route('promos.store'), ['code' => 'FIVE', 'type' => 'fixed', 'value' => '5.50', 'is_active' => '1'])->assertRedirect();

        $summer = mkIn($r, fn () => PromoCode::where('code', 'SUMMER20')->first());
        $five = mkIn($r, fn () => PromoCode::where('code', 'FIVE')->first());
        expect($summer->value)->toBe(20)->and($summer->min_order_cents)->toBe(1500)->and($five->value)->toBe(550);

        $this->actingAs($owner)->get(route('promos.index'))->assertOk()->assertSee('SUMMER20')->assertSee('20% off');
        $this->actingAs($owner)->post(route('promos.toggle', $five->id))->assertRedirect();
        expect(mkIn($r, fn () => $five->fresh()->is_active))->toBeFalse();
        $this->actingAs($owner)->delete(route('promos.destroy', $five->id))->assertRedirect(route('promos.index'));
        expect(mkIn($r, fn () => PromoCode::where('code', 'FIVE')->exists()))->toBeFalse();
    });

    it('validates codes and keeps them unique per restaurant only', function () {
        [$r] = mkShop();
        [$other] = mkShop();
        mkPromo($other, ['code' => 'SAME']);
        $owner = mkUser($r, 'manager');
        $this->actingAs($owner)->post(route('promos.store'), ['code' => 'SAME', 'type' => 'percent', 'value' => 10, 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('promos.store'), ['code' => 'SAME', 'type' => 'percent', 'value' => 10])->assertSessionHasErrors('code');
        $this->actingAs($owner)->post(route('promos.store'), ['code' => 'BIGONE', 'type' => 'percent', 'value' => 150])->assertSessionHasErrors('value');
        $this->actingAs($owner)->post(route('promos.store'), ['code' => 'AB', 'type' => 'fixed', 'value' => 1])->assertSessionHasErrors('code');
    });

    it('does not reach codes of another restaurant', function () {
        [$r] = mkShop();
        [$other] = mkShop();
        $theirs = mkPromo($other);
        $owner = mkUser($r, 'manager');
        $this->actingAs($owner)->get(route('promos.edit', $theirs->id))->assertNotFound();
        $this->actingAs($owner)->post(route('promos.toggle', $theirs->id))->assertNotFound();
        $this->actingAs($owner)->delete(route('promos.destroy', $theirs->id))->assertNotFound();
    });
});

describe('loyalty', function () {
    function mkLoyal(array $over = []): array
    {
        return mkShop([], array_merge(['loyalty_enabled' => true, 'loyalty_every' => 2, 'loyalty_reward_type' => 'percent', 'loyalty_reward_value' => '15'], $over));
    }

    it('rewards every Nth completed order with a single-use code for that guest only, and e-mails it', function () {
        [$r, $d] = mkLoyal();
        $first = mkOrder($r, $d, ['customer_email' => 'ada@example.com']);
        mkComplete($r, $first);
        expect(mkIn($r, fn () => PromoCode::count()))->toBe(0);

        $second = mkOrder($r, $d, ['customer_email' => 'ada@example.com']);
        mkComplete($r, $second);
        $reward = mkIn($r, fn () => PromoCode::first());
        expect($reward->type)->toBe('percent')->and($reward->value)->toBe(15)->and($reward->max_uses)->toBe(1)->and($reward->source_order_id)->toBe($second->id)->and($reward->isLoyaltyReward())->toBeTrue()
            ->and($reward->ends_at->isFuture())->toBeTrue();
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'loyalty_reward' && $m->hasTo('ada@example.com') && $m->vars['code'] === $reward->code);

        // Only Ada can use it, once.
        expect(fn () => mkOrder($r, $d, ['customer_email' => 'eve@example.com', 'customer_phone' => '+1 555 123 4567', 'promo_code' => $reward->code]))->toThrow(OrderException::class);
        $used = mkOrder($r, $d, ['customer_email' => 'ada@example.com', 'promo_code' => $reward->code]);
        expect($used->discount_cents)->toBe(150);
        expect(fn () => mkOrder($r, $d, ['customer_email' => 'ada@example.com', 'promo_code' => $reward->code]))->toThrow(OrderException::class);
    });

    it('can be redeemed with the phone number the reward was earned on', function () {
        [$r, $d] = mkLoyal();
        foreach ([1, 2] as $_) {
            mkComplete($r, mkOrder($r, $d));
        }
        $reward = mkIn($r, fn () => PromoCode::first());
        expect(mkOrder($r, $d, ['customer_phone' => '0 (555) 111 2222 ', 'promo_code' => $reward->code])->discount_cents)->toBe(150);
    });

    it('does nothing when switched off, and never twice for one order', function () {
        [$r, $d] = mkLoyal(['loyalty_enabled' => false]);
        foreach ([1, 2] as $_) {
            mkComplete($r, mkOrder($r, $d, ['customer_email' => 'a@example.com']));
        }
        expect(mkIn($r, fn () => PromoCode::count()))->toBe(0);

        [$r2, $d2] = mkLoyal();
        $o = null;
        foreach ([1, 2] as $_) {
            $o = mkOrder($r2, $d2, ['customer_email' => 'a@example.com']);
            mkComplete($r2, $o);
        }
        mkIn($r2, fn () => app(LoyaltyService::class)->onCompleted($r2, Order::find($o->id)));
        expect(mkIn($r2, fn () => PromoCode::count()))->toBe(1);
    });

    it('shows the reward on the guest’s order page', function () {
        [$r, $d] = mkLoyal();
        mkComplete($r, mkOrder($r, $d, ['customer_email' => 'a@example.com']));
        $o = mkOrder($r, $d, ['customer_email' => 'a@example.com']);
        mkComplete($r, $o);
        $code = mkIn($r, fn () => PromoCode::first()->code);

        $this->getJson("/r/{$r->slug}/order/{$o->token}/status")->assertOk()->assertJsonPath('reward.code', $code)->assertJsonPath('reward.text', '15% off');
    });

    it('saves loyalty settings with a platform ceiling on e-mails', function () {
        [$r] = mkShop();
        $owner = mkUser($r, 'manager');
        $this->actingAs($owner)->get(route('marketing.settings'))->assertOk();
        $this->actingAs($owner)->put(route('marketing.settings.update'), [
            'loyalty_enabled' => '1', 'loyalty_every' => 4, 'loyalty_reward_type' => 'fixed', 'loyalty_reward_value' => 5, 'loyalty_valid_days' => 30, 'campaign_daily_cap' => 100,
        ])->assertSessionHasNoErrors();

        $s = app(MarketingSettings::class)->for($r->fresh());
        expect($s['loyalty_enabled'])->toBeTrue()->and($s['loyalty_every'])->toBe(4)->and($s['reviews_enabled'])->toBeFalse();
        $this->actingAs($owner)->put(route('marketing.settings.update'), ['loyalty_every' => 4, 'loyalty_reward_type' => 'fixed', 'loyalty_reward_value' => 5, 'loyalty_valid_days' => 30, 'campaign_daily_cap' => 999999])->assertSessionHasErrors('campaign_daily_cap');
    });
});

describe('reviews', function () {
    it('invites a rating only after completion, once per order', function () {
        [$r, $d] = mkShop();
        $o = mkOrder($r, $d, ['customer_name' => 'Gus Guest']);
        $url = "/r/{$r->slug}/order/{$o->token}/review";

        $this->post($url, ['rating' => 5])->assertSessionHasErrors('review'); // not completed yet
        mkComplete($r, $o);
        $this->getJson("/r/{$r->slug}/order/{$o->token}/status")->assertJsonPath('review.open', true);

        $this->post($url, ['rating' => 4, 'comment' => '<b>Tasty</b> pizza', 'is_public' => '1'])->assertSessionHasNoErrors();
        $review = mkIn($r, fn () => Review::first());
        expect($review->rating)->toBe(4)->and($review->comment)->toBe('Tasty pizza')->and($review->author)->toBe('Gus')->and($review->is_public)->toBeTrue();

        $this->post($url, ['rating' => 1])->assertSessionHasErrors('review'); // already rated
        $this->getJson("/r/{$r->slug}/order/{$o->token}/status")->assertJsonPath('review.open', false)->assertJsonPath('review.rating', 4);
        expect(mkIn($r, fn () => Review::count()))->toBe(1);
    });

    it('rejects ratings out of range and respects the switch', function () {
        [$r, $d] = mkShop();
        $o = mkOrder($r, $d);
        mkComplete($r, $o);
        $url = "/r/{$r->slug}/order/{$o->token}/review";
        $this->post($url, ['rating' => 6])->assertSessionHasErrors('rating');
        $this->post($url, ['rating' => 0])->assertSessionHasErrors('rating');

        [$r2, $d2] = mkShop([], ['reviews_enabled' => false]);
        $o2 = mkOrder($r2, $d2);
        mkComplete($r2, $o2);
        $this->post("/r/{$r2->slug}/order/{$o2->token}/review", ['rating' => 5])->assertSessionHasErrors('review');
    });

    it('cannot rate another restaurant’s order through this restaurant’s link', function () {
        [$r, $d] = mkShop();
        [$other] = mkShop();
        $o = mkOrder($r, $d);
        mkComplete($r, $o);
        $this->post("/r/{$other->slug}/order/{$o->token}/review", ['rating' => 5])->assertNotFound();
    });

    it('asks for a rating by e-mail once, when allowed', function () {
        [$r, $d] = mkShop();
        mkComplete($r, mkOrder($r, $d, ['customer_email' => 'a@example.com']));
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'review_request' && $m->hasTo('a@example.com'));

        Mail::fake();
        [$r2, $d2] = mkShop([], ['review_request_email' => false]);
        mkComplete($r2, mkOrder($r2, $d2, ['customer_email' => 'b@example.com']));
        Mail::assertNotSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'review_request');
    });

    it('lists reviews with the average, flags low ratings and lets staff reply', function () {
        [$r, $d] = mkShop();
        foreach ([[5, 'Great'], [1, 'Cold food']] as [$stars, $text]) {
            $o = mkOrder($r, $d);
            mkComplete($r, $o);
            $this->post("/r/{$r->slug}/order/{$o->token}/review", ['rating' => $stars, 'comment' => $text]);
        }
        $manager = mkUser($r, 'manager');

        $this->actingAs($manager)->get(route('reviews.index'))->assertOk()->assertSee('Great')->assertSee('Cold food')->assertSee('3.0')->assertSee(__('marketing.low_flag'));
        $this->actingAs($manager)->get(route('reviews.index', ['filter' => 'low']))->assertSee('Cold food')->assertDontSee('Great');

        $low = mkIn($r, fn () => Review::where('rating', 1)->first());
        $this->actingAs($manager)->post(route('reviews.reply', $low->id), ['reply' => 'So sorry, we will fix it.'])->assertRedirect();
        expect(mkIn($r, fn () => $low->fresh()->replied_at))->not->toBeNull();
        $this->actingAs($manager)->get(route('reviews.index', ['filter' => 'unanswered']))->assertDontSee('Cold food');
        $this->getJson("/r/{$r->slug}/order/".mkIn($r, fn () => $low->order->token).'/status')->assertJsonPath('review.reply', 'So sorry, we will fix it.');
    });

    it('keeps reviews of other restaurants out of the list and out of reach', function () {
        [$r] = mkShop();
        [$other, $od] = mkShop();
        $o = mkOrder($other, $od);
        mkComplete($other, $o);
        $this->post("/r/{$other->slug}/order/{$o->token}/review", ['rating' => 2, 'comment' => 'Secret complaint']);
        $theirs = mkIn($other, fn () => Review::first());
        $manager = mkUser($r, 'manager');

        $this->actingAs($manager)->get(route('reviews.index'))->assertDontSee('Secret complaint');
        $this->actingAs($manager)->post(route('reviews.reply', $theirs->id), ['reply' => 'x'])->assertNotFound();
    });

    it('shows the average on the menu only with enough public reviews', function () {
        [$r, $d] = mkShop();
        foreach ([5, 4, 5] as $i => $stars) {
            $o = mkOrder($r, $d);
            mkComplete($r, $o);
            $this->post("/r/{$r->slug}/order/{$o->token}/review", ['rating' => $stars, 'is_public' => $i === 2 ? null : '1']);
        }
        // Only two are public: not shown yet.
        $this->get("/r/{$r->slug}")->assertOk()->assertDontSee('4.5 ');

        $o = mkOrder($r, $d);
        mkComplete($r, $o);
        $this->post("/r/{$r->slug}/order/{$o->token}/review", ['rating' => 4, 'is_public' => '1']);
        $this->get("/r/{$r->slug}")->assertOk()->assertSee('4.3')->assertSee('(3)');
    });
});

describe('campaigns', function () {
    function mkCampaign(Restaurant $r, array $attrs = []): Campaign
    {
        return mkIn($r, fn () => Campaign::create(array_merge(['name' => 'Spring', 'subject' => 'Hello {{name}}', 'body' => "Hi **{{name}}**, visit {{restaurant}}!\n\n<script>alert(1)</script>"], $attrs)));
    }

    it('only reaches guests who agreed, have an address, did not unsubscribe and ordered enough', function () {
        [$r] = mkShop();
        $yes = mkCustomer($r, ['orders_count' => 3]);
        mkCustomer($r, ['marketing_opt_in' => false]);
        mkCustomer($r, ['unsubscribed_at' => now()]);
        mkCustomer($r, ['email' => null, 'phone' => '+1 555 000 0000']);
        $few = mkCustomer($r, ['orders_count' => 1]);
        [$other] = mkShop();
        mkCustomer($other);

        $all = mkIn($r, fn () => app(CampaignService::class)->audience(new Campaign(['min_orders' => 0]))->pluck('id')->all());
        $regulars = mkIn($r, fn () => app(CampaignService::class)->audience(new Campaign(['min_orders' => 2]))->pluck('id')->all());
        expect($all)->toContain($yes->id, $few->id)->toHaveCount(2)->and($regulars)->toBe([$yes->id]);
    });

    it('sends personalised, escaped e-mails with an unsubscribe link, and records the result', function () {
        [$r] = mkShop();
        $c = mkCustomer($r, ['name' => 'Ada <b>']);
        $campaign = mkCampaign($r);
        $this->actingAs(mkUser($r, 'manager'))->post(route('campaigns.send', $campaign->id))->assertRedirect(route('campaigns.show', $campaign->id));

        Mail::assertSent(CampaignMail::class, 1);
        Mail::assertSent(CampaignMail::class, function (CampaignMail $m) use ($c) {
            $html = $m->render();

            return $m->hasTo($c->email) && $m->hasSubject('Hello Ada <b>') && str_contains($html, 'Ada &lt;b&gt;') && ! str_contains($html, '<script>')
                && str_contains($html, '/unsubscribe/'.$c->id) && str_contains($m->headers()->text['List-Unsubscribe'], '/unsubscribe/'.$c->id);
        });
        $fresh = mkIn($r, fn () => $campaign->fresh());
        expect($fresh->status)->toBe('sent')->and($fresh->sent_count)->toBe(1)->and($fresh->sent_at)->not->toBeNull();
    });

    it('stops at the daily cap and skips the rest', function () {
        [$r] = mkShop([], ['campaign_daily_cap' => 2]);
        foreach (range(1, 4) as $_) {
            mkCustomer($r);
        }
        $campaign = mkCampaign($r);
        $this->actingAs(mkUser($r, 'manager'))->post(route('campaigns.send', $campaign->id));

        Mail::assertSent(CampaignMail::class, 2);
        $fresh = mkIn($r, fn () => $campaign->fresh());
        expect($fresh->sent_count)->toBe(2)->and($fresh->skipped_count)->toBe(2);

        // A second campaign the same day has no room left.
        Mail::fake();
        $again = mkCampaign($r, ['name' => 'Again']);
        $this->actingAs(mkUser($r, 'manager'))->post(route('campaigns.send', $again->id));
        Mail::assertNothingSent();
    });

    it('rechecks consent at sending time', function () {
        [$r] = mkShop();
        $gone = mkCustomer($r);
        mkCustomer($r);
        $campaign = mkCampaign($r);
        mkIn($r, function () use ($r, $campaign, $gone) {
            $campaign->forceFill(['status' => 'sending'])->save();
            app(CampaignService::class)->audience($campaign)->get()->each(fn ($c) => CampaignRecipient::create(['campaign_id' => $campaign->id, 'customer_id' => $c->id]));
            app(CustomerService::class)->unsubscribe($gone);
            app(CampaignService::class)->deliver($r, $campaign);
        });

        Mail::assertSent(CampaignMail::class, 1);
        Mail::assertNotSent(CampaignMail::class, fn ($m) => $m->hasTo($gone->email));
    });

    it('cannot be sent twice or to nobody, and drafts only are editable', function () {
        [$r] = mkShop();
        $manager = mkUser($r, 'manager');
        $empty = mkCampaign($r, ['name' => 'Empty']);
        $this->actingAs($manager)->post(route('campaigns.send', $empty->id))->assertSessionHasErrors('campaign');

        mkCustomer($r);
        $campaign = mkCampaign($r);
        $this->actingAs($manager)->post(route('campaigns.send', $campaign->id));
        $this->actingAs($manager)->post(route('campaigns.send', $campaign->id))->assertForbidden();
        $this->actingAs($manager)->put(route('campaigns.update', $campaign->id), ['name' => 'x', 'subject' => 'x', 'body' => 'x'])->assertForbidden();
        Mail::assertSent(CampaignMail::class, 1);
    });

    it('creates, previews and sends a test to the writer', function () {
        [$r] = mkShop();
        $manager = mkUser($r, 'manager');
        $this->actingAs($manager)->post(route('campaigns.store'), ['name' => 'N', 'subject' => 'S', 'body' => 'Hello', 'min_orders' => 2])->assertRedirect();
        $campaign = mkIn($r, fn () => Campaign::first());
        expect($campaign->status)->toBe('draft')->and($campaign->min_orders)->toBe(2);

        $this->actingAs($manager)->get(route('campaigns.show', $campaign->id))->assertOk()->assertSee('Hello');
        $this->actingAs($manager)->post(route('campaigns.test', $campaign->id))->assertRedirect();
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo($manager->email) && $m->hasSubject('[Test] S'));
    });

    it('never touches another restaurant’s campaign', function () {
        [$r] = mkShop();
        [$other] = mkShop();
        $theirs = mkCampaign($other);
        $manager = mkUser($r, 'manager');
        $this->actingAs($manager)->get(route('campaigns.show', $theirs->id))->assertNotFound();
        $this->actingAs($manager)->post(route('campaigns.send', $theirs->id))->assertNotFound();
        $this->actingAs($manager)->delete(route('campaigns.destroy', $theirs->id))->assertNotFound();
    });
});

describe('unsubscribe', function () {
    it('needs a valid signature', function () {
        [$r] = mkShop();
        $c = mkCustomer($r);
        $this->get("/unsubscribe/{$c->id}")->assertForbidden();
        $this->post("/unsubscribe/{$c->id}")->assertForbidden();
        $this->get(URL::signedRoute('marketing.unsubscribe', ['customer' => $c->id + 1]).'&x=1')->assertForbidden();
    });

    it('asks first, then unsubscribes, including the one-click post from mail apps', function () {
        [$r] = mkShop();
        $c = mkCustomer($r);
        $url = URL::signedRoute('marketing.unsubscribe', ['customer' => $c->id]);

        $this->get($url)->assertOk()->assertSee($r->name);
        expect(mkIn($r, fn () => $c->fresh()->canBeEmailed()))->toBeTrue(); // viewing the page changes nothing

        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk()->assertSee(__('marketing.unsub_done'));
        expect(mkIn($r, fn () => $c->fresh()->canBeEmailed()))->toBeFalse()->and(mkIn($r, fn () => $c->fresh()->unsubscribed_at))->not->toBeNull();
        $this->get($url)->assertOk()->assertSee(__('marketing.unsub_done'));
    });
});
