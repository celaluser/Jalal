<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Cache::flush();
});

function qbShop(int $categories, int $perCategory): array
{
    $r = Restaurant::create(['name' => 'Budget', 'slug' => 'qb'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => array_fill_keys(Plan::FEATURES, true)]), now()->addMonth());
    $owner = User::factory()->create(['restaurant_id' => $r->id]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $owner->assignRole(Permissions::OWNER);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));

    app(TenantContext::class)->runAs($r, function () use ($categories, $perCategory) {
        foreach (range(1, $categories) as $c) {
            $cat = Category::create(['name' => ['en' => "Cat $c"], 'sort' => $c]);
            foreach (range(1, $perCategory) as $p) {
                Product::create(['category_id' => $cat->id, 'name' => ['en' => "Dish $c-$p"], 'price' => 5 + $p, 'sort' => $p]);
            }
        }
    });

    return [$r, $owner];
}

function qbCount(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    if (getenv('QB_TRACE')) {
        $seen = 0;
        DB::listen(function ($q) use (&$seen) {
            if (str_contains($q->sql, 'from "restaurants" where "restaurants"."id"') && ++$seen === 20) {
                file_put_contents('/tmp/trace.txt', collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40))->map(fn ($f) => ($f['file'] ?? '').':'.($f['line'] ?? '').' '.($f['function'] ?? ''))->filter(fn ($l) => str_contains($l, '/app/') || str_contains($l, 'storage/framework/views'))->implode("\n"));
            }
        });
    }
    $request();
    $log = DB::getQueryLog();
    $n = count($log);
    if (getenv('QB_DUMP')) {
        file_put_contents('/tmp/qlog.txt', collect($log)->pluck('query')->map(fn ($q) => preg_replace("/\d+/", 'N', $q))->countBy()->sortDesc()->take(8)->map(fn ($c, $q) => "$c  $q")->implode("\n"));
    }
    DB::disableQueryLog();

    return $n;
}

it('does not query more per dish as the menu grows', function () {
    [$small] = qbShop(2, 2);
    [$big] = qbShop(8, 10);

    $this->get('/r/'.$small->slug)->assertOk();
    Cache::flush();
    $a = qbCount(fn () => $this->get('/r/'.$small->slug)->assertOk());
    Cache::flush();
    $b = qbCount(fn () => $this->get('/r/'.$big->slug)->assertOk());

    expect($b - $a)->toBeLessThanOrEqual(5, "guest menu: {$a} queries for 4 dishes, {$b} for 80");
});

it('keeps staff pages flat as data grows', function () {
    [$r, $owner] = qbShop(8, 10);
    [$r2, $owner2] = qbShop(1, 1);

    foreach (['menu.index', 'menu.stock.index', 'dashboard', 'customers.index', 'orders.pos.index', 'reports.index', 'reports.menu', 'reports.operations', 'reports.visits', 'tables.index', 'menu.option-groups.index'] as $route) {
        if (! Route::has($route)) {
            continue;
        }
        $this->actingAs($owner2)->get(route($route))->assertOk();
        $small = qbCount(fn () => $this->actingAs($owner2)->get(route($route))->assertOk());
        $big = qbCount(fn () => $this->actingAs($owner)->get(route($route))->assertOk());
        expect($big - $small)->toBeLessThanOrEqual(8, "{$route}: {$small} vs {$big} queries");
    }
});
