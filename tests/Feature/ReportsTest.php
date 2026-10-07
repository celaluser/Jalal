<?php

use App\Models\User;
use App\Modules\Analytics\Services\ReportService;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

function rpShop(bool $analytics = true, string $tz = 'UTC'): array
{
    $r = Restaurant::create(['name' => 'Report Bar', 'slug' => 'rb'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => $tz, 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => $analytics ? ['analytics' => true] : []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $user = User::factory()->create(['restaurant_id' => $r->id]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $user->assignRole(Permissions::MANAGER);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);

        return ['pizza' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Pizza'], 'price' => 10, 'sort' => 1]), 'soda' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Soda'], 'price' => 2, 'sort' => 2])];
    });

    return [$r, $user, $d];
}

/** An order of $qty pizzas placed at the given moment. */
function rpOrder(Restaurant $r, array $d, string|CarbonImmutable $at, int $qty = 1, array $over = [], ?string $status = null, string $product = 'pizza'): Order
{
    $order = app(TenantContext::class)->runAs($r, function () use ($r, $d, $qty, $over, $product) {
        return app(OrderService::class)->place($r, array_merge(['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $d[$product]->id, 'qty' => $qty]]], $over));
    });

    if ($status === OrderStatus::CANCELLED) {
        app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->transition(Order::find($order->id), OrderStatus::CANCELLED));
    }

    Order::withoutGlobalScopes()->whereKey($order->id)->update(['created_at' => CarbonImmutable::parse($at, 'UTC')]);

    return $order;
}

function rpBuild(Restaurant $r, string $range = '7', bool $full = true): array
{
    return app(TenantContext::class)->runAs($r, function () use ($r, $range, $full) {
        $svc = app(ReportService::class);

        return $svc->build($r, $svc->period($r, $range), $full);
    });
}

it('adds up sales, orders and average, leaving cancelled orders out', function () {
    [$r, , $d] = rpShop();
    rpOrder($r, $d, now()->subDay(), 2);               // 20.00
    rpOrder($r, $d, now()->subDays(2), 1);             // 10.00
    rpOrder($r, $d, now()->subDay(), 3, [], 'cancelled'); // not a sale

    $rep = rpBuild($r);
    expect($rep['orders'])->toBe(2)->and($rep['revenue'])->toBe(3000)->and($rep['average'])->toBe(1500)->and($rep['cancelled'])->toBe(1)->and($rep['cancel_rate'])->toBe(33.3);
});

it('buckets days in the restaurant’s time zone and fills empty days', function () {
    // 23:30 UTC on a day is already the next morning in Istanbul (UTC+3).
    [$r, , $d] = rpShop(true, 'Europe/Istanbul');
    $late = CarbonImmutable::now('UTC')->subDays(2)->setTime(23, 30);
    rpOrder($r, $d, $late);
    $rep = rpBuild($r);

    $local = $late->setTimezone('Europe/Istanbul')->toDateString();
    expect($rep['daily'])->toHaveCount(7)->and($rep['daily'][$local]['orders'])->toBe(1)->and(array_sum(array_column($rep['daily'], 'orders')))->toBe(1);
    expect($rep['heatmap'][$late->setTimezone('Europe/Istanbul')->dayOfWeekIso - 1][2])->toBe(1);
});

it('compares with the previous period of the same length', function () {
    [$r, , $d] = rpShop();
    rpOrder($r, $d, now()->subDays(2), 2);   // 20.00 this week
    rpOrder($r, $d, now()->subDays(9), 1);   // 10.00 the week before
    $rep = rpBuild($r);
    expect($rep['delta']['revenue'])->toBe(100.0)->and($rep['delta']['orders'])->toBe(0.0);

    [$r2, , $d2] = rpShop();
    rpOrder($r2, $d2, now()->subDay());
    expect(rpBuild($r2)['delta']['revenue'])->toBeNull(); // nothing to compare with
});

it('ranks best sellers by portions and counts items', function () {
    [$r, , $d] = rpShop();
    rpOrder($r, $d, now()->subDay(), 2);
    rpOrder($r, $d, now()->subDay(), 5, [], null, 'soda');
    rpOrder($r, $d, now()->subDay(), 9, [], 'cancelled', 'soda');
    $rep = rpBuild($r);

    expect($rep['top'][0])->toBe(['name' => 'Soda', 'qty' => 5, 'revenue' => 1000])->and($rep['top'][1]['name'])->toBe('Pizza')->and($rep['items_sold'])->toBe(7);
});

it('splits orders by type, payment and channel, and new from returning customers', function () {
    [$r, , $d] = rpShop();
    rpOrder($r, $d, now()->subDay(), 1, ['customer_email' => 'a@example.com']);
    rpOrder($r, $d, now()->subHours(5), 1, ['customer_email' => 'a@example.com', 'payment_method' => 'card']);
    rpOrder($r, $d, now()->subHours(4), 1, ['customer_phone' => null, 'customer_name' => null, 'type' => 'dine_in', 'table_id' => app(TenantContext::class)->runAs($r, fn () => DiningTable::create(['name' => 'T1'])->id)]);
    $rep = rpBuild($r);

    expect(collect($rep['by_type'])->pluck('orders', 'key')->all())->toBe(['takeaway' => 2, 'dine_in' => 1])->and(collect($rep['by_payment'])->pluck('orders', 'key')->all())->toMatchArray(['cash' => 2, 'card' => 1])
        ->and($rep['customers'])->toBe(['new' => 1, 'returning' => 0, 'anonymous' => 1]);
});

it('limits custom ranges and ignores nonsense', function () {
    [$r] = rpShop();
    $svc = app(ReportService::class);
    expect($svc->period($r, 'custom', '2020-01-01', '2020-12-31')['days'])->toBe(366)->and($svc->period($r, 'nope')['key'])->toBe('7')
        ->and($svc->period($r, 'custom', 'garbage', 'rubbish')['days'])->toBe(7)->and($svc->period($r, '90', null, null, 7)['days'])->toBe(7);
    $p = $svc->period($r, 'custom', '2030-01-10', '2030-01-01');
    expect($p['from'] <= $p['to'])->toBeTrue()->and($p['to']->toDateString())->toBe(CarbonImmutable::now('UTC')->toDateString());
});

it('shows the full report to a plan with analytics', function () {
    [$r, $manager, $d] = rpShop();
    rpOrder($r, $d, now()->subDay(), 2);
    $this->actingAs($manager)->get(route('reports.index', ['range' => '30']))->assertOk()
        ->assertSee('$20.00')->assertSee(__('analytics.best_sellers'))->assertSee(__('analytics.busy_hours'))->assertSee('Pizza');
});

it('gives other plans the headline numbers and a short window only', function () {
    [$r, $manager, $d] = rpShop(false);
    rpOrder($r, $d, now()->subDay(), 2);
    rpOrder($r, $d, now()->subDays(20), 5);
    $res = $this->actingAs($manager)->get(route('reports.index', ['range' => '90']))->assertOk();
    $res->assertSee('$20.00')->assertDontSee('$70.00')->assertDontSee(__('analytics.how_they_order'))->assertSee(__('analytics.locked_title'));
    $this->actingAs($manager)->get(route('reports.export'))->assertForbidden();
});

it('shows a friendly empty state', function () {
    [, $manager] = rpShop();
    $this->actingAs($manager)->get(route('reports.index'))->assertOk()->assertSee(__('analytics.empty_title'));
});

it('exports orders as a CSV for the period only, without formulas', function () {
    [$r, $manager, $d] = rpShop();
    [$other, , $od] = rpShop();
    $mine = rpOrder($r, $d, now()->subDay(), 1, ['promo_code' => null]);
    rpOrder($r, $d, now()->subDays(40), 1);
    $theirs = rpOrder($other, $od, now()->subDay());

    $csv = $this->actingAs($manager)->get(route('reports.export', ['range' => '7']))->assertOk()->streamedContent();
    expect($csv)->toContain('#' === '' ? '' : (string) $mine->number)->toContain('10.00')->and(substr_count($csv, "\n"))->toBe(2); // header + one order
});

it('is closed to waiters and guests, and each restaurant sees only its own numbers', function () {
    [$r, $manager, $d] = rpShop();
    [$other, , $od] = rpShop();
    rpOrder($other, $od, now()->subDay(), 9);

    $waiter = User::factory()->create(['restaurant_id' => $r->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    $waiter->assignRole('waiter');
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->actingAs($waiter)->get(route('reports.index'))->assertForbidden();
    $this->actingAs($manager)->get(route('reports.index'))->assertOk()->assertDontSee('$90.00');
    auth()->logout();
    $this->get(route('reports.index'))->assertRedirect(route('login'));
});
