<?php

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', ResolveTenant::class])
        ->get('/r/{restaurant}', fn () => app(TenantContext::class)->get()->name);
    Route::middleware(['web', ResolveTenant::class])
        ->get('/probe', fn () => app(TenantContext::class)->get()->name);
});

it('resolves a restaurant from the slug path', function () {
    Restaurant::create(['name' => 'Pizza Place', 'slug' => 'pizza']);

    $this->get('/r/pizza')->assertOk()->assertSee('Pizza Place');
});

it('returns 404 for unknown and suspended restaurants', function () {
    Restaurant::create(['name' => 'Gone', 'slug' => 'gone', 'status' => Restaurant::STATUS_SUSPENDED]);

    $this->get('/r/nope')->assertNotFound();
    $this->get('/r/gone')->assertNotFound();
});

it('resolves a subdomain when enabled', function () {
    config(['tenancy.subdomains_enabled' => true, 'tenancy.base_domain' => 'menu.test']);
    Restaurant::create(['name' => 'Sub Cafe', 'slug' => 'sub', 'subdomain' => 'sub']);

    $this->get('http://sub.menu.test/probe')->assertOk()->assertSee('Sub Cafe');
});

it('ignores subdomains when the feature is off', function () {
    config(['tenancy.subdomains_enabled' => false, 'tenancy.base_domain' => 'menu.test']);
    Restaurant::create(['name' => 'Sub Cafe', 'slug' => 'sub', 'subdomain' => 'sub']);

    $this->get('http://sub.menu.test/probe')->assertNotFound();
});

it('only resolves a custom domain once it is verified', function () {
    config(['tenancy.custom_domains_enabled' => true]);
    Restaurant::create(['name' => 'Custom', 'slug' => 'custom', 'custom_domain' => 'menu.custom.test']);

    $this->get('http://menu.custom.test/probe')->assertNotFound();

    Restaurant::where('slug', 'custom')->update(['domain_verified_at' => now()]);

    $this->get('http://menu.custom.test/probe')->assertOk()->assertSee('Custom');
});
