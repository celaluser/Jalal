<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

/*
| Every form and action of the panels is sent empty, with text where numbers belong, with arrays where text belongs,
| and with absurd lengths. A rejected request (validation error, 403, 404, 419 ...) is fine; a server error is a bug.
*/
function fuzzBodies(): array
{
    return [
        'empty' => [],
        'wrong types' => ['name' => ['x'], 'title' => ['x'], 'email' => 12, 'price' => 'abc', 'amount' => 'abc', 'qty' => 'many', 'id' => [], 'status' => ['a'], 'slug' => ['x'], 'locale' => ['x'], 'plan_id' => 'zzz', 'restaurant_id' => 'zzz', 'questions' => 'text', 'blocks_json' => ['x'], 'gallery_json' => 5, 'limits' => 'x', 'features' => 'x', 'lines' => 'x', 'a' => 'x'],
        'long text' => ['name' => str_repeat('é', 5000), 'title' => str_repeat('x', 5000), 'body' => str_repeat('y', 50000), 'note' => str_repeat('z', 5000), 'email' => str_repeat('a', 400).'@x.test'],
        'nulls and zero' => ['name' => null, 'price' => 0, 'amount' => -1, 'qty' => 0, 'status' => '', 'plan_id' => 0, 'restaurant_id' => 0],
    ];
}

it('answers odd form input with a refusal, never with a server error', function (string $who) {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);

    $r = Restaurant::create(['name' => 'Fuzz Bistro', 'slug' => 'fuzz', 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'pf', 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => []]), now()->addMonth());
    $user = User::factory()->create(['restaurant_id' => $who === 'owner' ? $r->id : null]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($who === 'owner' ? $r->id : config('tenancy.platform_team_id'));
    $user->assignRole($who === 'owner' ? Permissions::OWNER : Permissions::SUPER_ADMIN);
    $this->actingAs($user);

    // Things that would end the session or wipe the whole test database are not what is being tested.
    $skip = '#^(logout|impersonation|install|admin/system/(tool|clear)|admin/backups|admin/addons|admin/update|admin/restaurants/\{restaurant\}/impersonate|cron|api/|hub/|webhooks|print/)#';
    $failures = [];

    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        $uri = $route->uri();
        $middleware = implode(' ', $route->gatherMiddleware());

        if (! str_contains($middleware, 'tenant.user') || preg_match($skip, $uri) === 1) {
            continue;
        }

        $path = '/'.preg_replace('/\{[^}?]+\??\}/', '1', $uri);

        foreach (array_diff($route->methods(), ['HEAD', 'GET']) as $method) {
            foreach (fuzzBodies() as $label => $body) {
                $status = $this->withHeaders(['Accept' => 'application/json'])->call($method, $path, $body)->getStatusCode();

                if ($status >= 500) {
                    $failures[] = "$method $path ($label) → $status";
                }
            }
        }
    }

    expect($failures)->toBe([]);
})->with(['owner', 'admin'])->group('slow');

it('answers odd query strings on every page with a normal page, never a server error', function (string $who) {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);

    $r = Restaurant::create(['name' => 'Query Bistro', 'slug' => 'query', 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'pq', 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => []]), now()->addMonth());

    if ($who !== 'guest') {
        $user = User::factory()->create(['restaurant_id' => $who === 'owner' ? $r->id : null]);
        $reg = app(PermissionRegistrar::class);
        $reg->setPermissionsTeamId($who === 'owner' ? $r->id : config('tenancy.platform_team_id'));
        $user->assignRole($who === 'owner' ? Permissions::OWNER : Permissions::SUPER_ADMIN);
        $this->actingAs($user);
    }

    $queries = [
        '?q[]=x&search[]=x&filter[]=a&status[]=a&sort[]=name&lang[]=tr&page[]=2&locale[]=en&group[]=x&file[]=x&level[]=x&type[]=x&date[]=x&from[]=x&to[]=x&token[]=x&ref[]=x&coupon[]=x',
        '?page=abc&per_page=-5&sort=%00&q='.str_repeat('é', 3000).'&lang=%F0%9F%98%80&date=9999-99-99&from=nope&to=nope',
    ];
    $failures = [];

    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        $uri = $route->uri();

        if (! in_array('GET', $route->methods(), true) || str_contains($uri, '{') || preg_match('#^(logout|install|up$|cron|_|storage|api/|hub|print|impersonation|admin/system/backups|.*(export|download|pdf|csv|sample)|menu/import/sample)#', $uri) === 1) {
            continue;
        }

        foreach ($queries as $q) {
            $status = $this->get('/'.$uri.$q)->getStatusCode();

            // 503 is an honest "not set up" answer (for example the AI endpoints without a key).
            if ($status >= 500 && $status !== 503) {
                $failures[] = "/$uri".substr($q, 0, 30)." → $status";
            }
        }
    }

    expect($failures)->toBe([]);
})->with(['guest', 'owner', 'admin'])->group('slow');

it('answers odd requests to the public pages of a restaurant with a refusal, never a server error', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    $r = Restaurant::create(['name' => 'Public Bistro', 'slug' => 'fz', 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now(), 'site_settings' => ['site_enabled' => true]]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'pz', 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => ['reservations' => true]]), now()->addMonth());

    $failures = [];
    $queries = ['', '?q[]=x&lang[]=tr&t[]=1&category[]=1&diet[]=x&sort[]=a&o[]=x&thanks[]=1', '?lang=%00&t=abc&category=zzz&diet=%F0%9F%98%80&date=9999-99-99&party=-4'];

    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        $uri = $route->uri();

        if (! str_starts_with($uri, 'r/{restaurant}') || preg_match('#(webhook|pay/.+/return|\.png|sw\.js|print/|pay/\{)#', $uri) === 1) {
            continue;
        }

        $path = '/'.preg_replace(['/\{restaurant\}/', '/\{token\}/', '/\{[^}?]+\??\}/'], ['fz', str_repeat('a', 24), '1'], $uri);

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            foreach ($method === 'GET' ? $queries : [''] as $q) {
                foreach ($method === 'GET' ? [[]] : array_values(fuzzBodies()) as $body) {
                    $status = $this->withHeaders(['Accept' => 'application/json'])->call($method, $path.$q, $body)->getStatusCode();

                    if ($status >= 500 && $status !== 503) {
                        $failures[] = "$method $path".substr($q, 0, 20)." → $status";
                    }
                }
            }
        }
    }

    expect($failures)->toBe([]);
})->group('slow');
