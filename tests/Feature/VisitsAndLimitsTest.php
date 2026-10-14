<?php

use App\Models\User;
use App\Modules\Analytics\Services\MenuVisits;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Billing\Services\UsageReport;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

function vlShop(array $limits = [], bool $analytics = true): array
{
    $r = Restaurant::create(['name' => 'Visit Bar', 'slug' => 'vl'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => $limits, 'features' => $analytics ? ['analytics' => true] : []]), now()->addMonth());
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);

        return ['pizza' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Pizza'], 'price' => 10, 'sort' => 1]), 'table' => DiningTable::create(['name' => 'T1'])];
    });

    return [$r, $d];
}

function vlOrder(Restaurant $r, array $d, string $source = 'qr')
{
    return app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $d['pizza']->id, 'qty' => 1]]], $source));
}

function vlCount(Restaurant $r, string $kind): int
{
    return (int) DB::table('menu_visits')->where('restaurant_id', $r->id)->where('kind', $kind)->sum('count');
}

it('counts menu views and table scans as hourly counters, once per session window, ignoring crawlers and kiosks', function () {
    [$r, $d] = vlShop();
    $token = app(TenantContext::class)->runAs($r, fn () => $d['table']->token);

    $this->get("/r/{$r->slug}")->assertOk();
    $this->get("/r/{$r->slug}")->assertOk(); // reload: same session, not counted again
    expect(vlCount($r, 'view'))->toBe(1);

    $this->get("/r/{$r->slug}/t/{$token}")->assertRedirect();
    expect(vlCount($r, 'scan'))->toBe(1);

    $this->flushSession();
    $this->withHeader('User-Agent', 'Googlebot/2.1')->get("/r/{$r->slug}")->assertOk();
    $this->withHeader('User-Agent', 'Mozilla/5.0')->get("/r/{$r->slug}?kiosk=1")->assertOk();
    expect(vlCount($r, 'view'))->toBe(1);

    $this->flushSession();
    $this->get("/r/{$r->slug}")->assertOk();
    expect(vlCount($r, 'view'))->toBe(2)->and(DB::table('menu_visits')->where('restaurant_id', $r->id)->count())->toBeLessThanOrEqual(3);
});

it('keeps counts per restaurant and stores nothing about the visitor', function () {
    [$a] = vlShop();
    [$b] = vlShop();
    $this->get("/r/{$a->slug}")->assertOk();
    expect(vlCount($a, 'view'))->toBe(1)->and(vlCount($b, 'view'))->toBe(0)->and(array_keys((array) DB::table('menu_visits')->first()))->toBe(['id', 'restaurant_id', 'day', 'hour', 'table_id', 'kind', 'count']);
});

it('shows the report to owners with the analytics feature', function () {
    [$r, $d] = vlShop();
    app(MenuVisits::class)->bump($r, 'view', 0, now());
    app(MenuVisits::class)->bump($r, 'scan', app(TenantContext::class)->runAs($r, fn () => $d['table']->id), now());
    vlOrder($r, $d);
    $u = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $u->assignRole('restaurant_owner');
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->actingAs($u)->get(route('reports.visits'))->assertOk()->assertSee('QR scans & views')->assertSee('Scans per table')->assertSee('T1');

    [$basic] = vlShop([], false);
    $b = User::factory()->create(['restaurant_id' => $basic->id]);
    $reg->setPermissionsTeamId($basic->id);
    $b->assignRole('restaurant_owner');
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->actingAs($b)->get(route('reports.visits'))->assertForbidden();
});

describe('monthly plan limits', function () {
    it('stops guest orders once the monthly cap is reached, but not staff, and ignores cancelled orders', function () {
        [$r, $d] = vlShop(['orders_per_month' => 2]);
        vlOrder($r, $d);
        $second = vlOrder($r, $d);
        expect(fn () => vlOrder($r, $d))->toThrow(OrderException::class);
        expect(fn () => vlOrder($r, $d, 'api'))->toThrow(OrderException::class);
        $staffOrder = vlOrder($r, $d, 'staff');
        expect($staffOrder)->not->toBeNull();

        app(TenantContext::class)->runAs($r, function () use ($second, $staffOrder) {
            $second->forceFill(['status' => 'cancelled'])->save();
            $staffOrder->forceFill(['status' => 'cancelled'])->save();
        });
        expect(vlOrder($r, $d))->not->toBeNull(); // cancelled orders free their places
    });

    it('does not cap anything when the limit is empty, and counts per restaurant', function () {
        [$free, $fd] = vlShop();
        [$capped, $cd] = vlShop(['orders_per_month' => 1]);
        foreach (range(1, 4) as $i) {
            vlOrder($free, $fd);
        }
        vlOrder($capped, $cd);
        expect(fn () => vlOrder($capped, $cd))->toThrow(OrderException::class);
        expect(vlOrder($free, $fd))->not->toBeNull();
    });

    it('answers the guest checkout with a plain message', function () {
        [$r, $d] = vlShop(['orders_per_month' => 1]);
        vlOrder($r, $d);
        $res = $this->postJson("/r/{$r->slug}/order", ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $d['pizza']->id, 'qty' => 1]]]);
        $res->assertStatus(422)->assertJsonPath('error', 'plan_limit')->assertJsonPath('message', 'We cannot take more online orders this month. Please order with the staff.');
    });

    it('takes the menu offline after the monthly view cap and brings it back next month', function () {
        [$r] = vlShop(['scans_per_month' => 2]);
        foreach (range(1, 2) as $i) {
            $this->flushSession();
            $this->get("/r/{$r->slug}")->assertOk();
        }
        $this->flushSession();
        $this->get("/r/{$r->slug}")->assertStatus(503);

        $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addHour());
        $this->flushSession();
        $this->get("/r/{$r->slug}")->assertOk();
    });

    it('shows the new limits in the plan form and usage', function () {
        expect(Plan::LIMITS)->toContain('orders_per_month', 'scans_per_month');
        [$r, $d] = vlShop(['orders_per_month' => 5, 'scans_per_month' => 100]);
        vlOrder($r, $d);
        $usage = app(TenantContext::class)->runAs($r, fn () => collect(app(UsageReport::class)->for($r))->keyBy('key'));
        expect($usage['orders_per_month']['used'])->toBe(1)->and($usage['orders_per_month']['limit'])->toBe(5);
    });
});
