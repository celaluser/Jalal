<?php

use App\Models\User;
use App\Modules\Analytics\Services\MenuEngineering;
use App\Modules\Analytics\Services\ReportService;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Mail::fake();
});

/** Four dishes: a cheap-cost bestseller, a low-margin bestseller, a profitable rarity, a poor rarity, plus one without cost price. */
function meShop(bool $analytics = true): array
{
    $owner = User::factory()->create();
    $r = Restaurant::create(['name' => 'Eng Eats', 'slug' => 'me'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'onboarded_at' => now(), 'timezone' => 'UTC', 'owner_id' => $owner->id]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => $analytics ? ['analytics' => true] : []]), now()->addMonth());
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Mains'], 'sort' => 1]);
        $mk = fn ($n, $price, $cost) => Product::create(['category_id' => $cat->id, 'name' => ['en' => $n], 'price' => $price, 'cost_price' => $cost, 'sort' => 1]);

        return ['star' => $mk('Star', 10, 2), 'horse' => $mk('Horse', 10, 9), 'puzzle' => $mk('Puzzle', 20, 4), 'dog' => $mk('Dog', 10, 9), 'free' => $mk('Free', 5, null)];
    });

    return [$r, $d, $owner];
}

function meSell(Restaurant $r, array $d, string $key, int $qty): void
{
    app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $d[$key]->id, 'qty' => $qty]]]));
}

function meBuild(Restaurant $r): array
{
    return app(TenantContext::class)->runAs($r, fn () => app(MenuEngineering::class)->build($r, app(ReportService::class)->period($r, '30')));
}

it('classifies dishes into stars, plowhorses, puzzles and dogs from popularity and margin', function () {
    [$r, $d] = meShop();
    meSell($r, $d, 'star', 10);
    meSell($r, $d, 'horse', 10);
    meSell($r, $d, 'puzzle', 1);
    meSell($r, $d, 'dog', 1);
    meSell($r, $d, 'free', 3);

    $data = meBuild($r);
    $classes = collect($data['items'])->pluck('class', 'name');
    expect($classes->all())->toEqualCanonicalizing(['Star' => 'star', 'Puzzle' => 'puzzle', 'Dog' => 'dog', 'Horse' => 'plowhorse'])
        ->and(collect($data['unclassified'])->pluck('name')->all())->toBe(['Free']);

    $star = collect($data['items'])->firstWhere('name', 'Star');
    expect($star['unit_margin'])->toBe(800)->and($star['margin'])->toBe(8000)->and($star['cost'])->toBe(2000);
    // cost of costed dishes: 10*2 + 10*9 + 4 + 9 = 123 on 10*10+10*10+20+10 = 230 revenue
    expect($data['food_cost_percent'])->toBe(53.5)->and($data['categories'][0])->toMatchArray(['name' => 'Mains', 'qty' => 25]);
});

it('ignores cancelled orders and other restaurants', function () {
    [$r, $d] = meShop();
    [$other, $od] = meShop();
    meSell($other, $od, 'star', 50);
    meSell($r, $d, 'star', 2);
    app(TenantContext::class)->runAs($r, fn () => \App\Modules\Orders\Models\Order::first()->forceFill(['status' => 'cancelled'])->save());
    expect(meBuild($r)['items'])->toBe([])->and(meBuild($r)['unclassified'])->toBe([]);
});

it('shows the page and CSV to owners with the analytics feature only, and escapes formulas', function () {
    [$r, $d, $owner] = meShop();
    app(TenantContext::class)->runAs($r, fn () => $d['star']->update(['name' => ['en' => '=HYPERLINK("x")']]));
    meSell($r, $d, 'star', 3);
    $u = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $u->assignRole('restaurant_owner');
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));

    $this->actingAs($u)->get(route('reports.menu'))->assertOk()->assertSee('Menu engineering')->assertSee('Stars');
    $csv = $this->actingAs($u)->get(route('reports.menu.export'))->assertOk()->streamedContent();
    expect($csv)->toContain("'=HYPERLINK")->not->toContain(",=HYPERLINK");

    [$basic, , ] = meShop(false);
    $b = User::factory()->create(['restaurant_id' => $basic->id]);
    $reg->setPermissionsTeamId($basic->id);
    $b->assignRole('restaurant_owner');
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->actingAs($b)->get(route('reports.menu'))->assertForbidden();
});

describe('weekly digest', function () {
    it('mails the owner last week\'s numbers once, skipping quiet or opted-out restaurants', function () {
        [$r, $d, $owner] = meShop();
        [$quiet] = meShop();
        [$off, $offd] = meShop();
        $off->update(['marketing_settings' => ['weekly_digest' => false]]);
        meSell($r, $d, 'star', 2);
        meSell($off, $offd, 'star', 2);

        $this->artisan('reports:digest')->assertSuccessful();
        Mail::assertSent(TemplatedMail::class, 1);
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->hasTo($owner->email));
    });
});

describe('team and tables report', function () {
    it('counts what each signed-in person did and how tables turn over', function () {
        [$r, $d] = meShop();
        $ana = User::factory()->create(['restaurant_id' => $r->id, 'name' => 'Ana']);
        $bob = User::factory()->create(['restaurant_id' => $r->id, 'name' => 'Bob']);
        $table = app(TenantContext::class)->runAs($r, fn () => \App\Modules\Tables\Models\DiningTable::create(['name' => 'T7']));
        $place = fn () => app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, ['type' => 'dine_in', 'table_id' => $table->id, 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $d['star']->id, 'qty' => 1]]]));
        $o1 = $place();
        $o2 = $place();
        app(TenantContext::class)->runAs($r, function () use ($o1, $o2, $ana, $bob) {
            $svc = app(OrderService::class);
            $svc->transition($o1, 'accepted', $ana);
            $svc->transition($o1, 'preparing', $ana);
            $svc->transition($o1, 'ready', $ana);
            $svc->transition($o1, 'completed', $ana);
            $svc->transition($o2, 'accepted', $bob);
            $svc->transition($o2, 'cancelled', $bob, 'test');
            \App\Modules\Orders\Models\Order::whereKey($o1->id)->update(['created_at' => now()->subMinutes(50), 'completed_at' => now()]);
        });

        $data = app(TenantContext::class)->runAs($r, fn () => app(\App\Modules\Analytics\Services\OperationsReport::class)->build($r, app(ReportService::class)->period($r, '30')));
        $ana = collect($data['staff'])->firstWhere('name', 'Ana');
        $bob = collect($data['staff'])->firstWhere('name', 'Bob');
        expect($ana)->toMatchArray(['accepted' => 1, 'ready' => 1, 'completed' => 1, 'cancelled' => 0])->and($bob)->toMatchArray(['accepted' => 1, 'cancelled' => 1, 'completed' => 0])
            ->and($data['tables'])->toHaveCount(1)->and($data['tables'][0])->toMatchArray(['table' => 'T7', 'orders' => 1, 'revenue' => 1000, 'avg_minutes' => 50]);
    });

    it('is shown to owners with the analytics feature only', function () {
        [$r] = meShop();
        $u = User::factory()->create(['restaurant_id' => $r->id]);
        $reg = app(PermissionRegistrar::class);
        $reg->setPermissionsTeamId($r->id);
        $u->assignRole('restaurant_owner');
        $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));
        $this->actingAs($u)->get(route('reports.operations'))->assertOk()->assertSee('Team & tables')->assertSee('No team activity');

        [$basic] = meShop(false);
        $b = User::factory()->create(['restaurant_id' => $basic->id]);
        $reg->setPermissionsTeamId($basic->id);
        $b->assignRole('restaurant_owner');
        $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));
        $this->actingAs($b)->get(route('reports.operations'))->assertForbidden();
    });
});
