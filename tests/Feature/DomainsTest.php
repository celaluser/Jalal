<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Language;
use App\Modules\Tenancy\Contracts\DnsResolver;
use App\Modules\Tenancy\Models\Restaurant;
use App\Modules\Tenancy\Services\DomainService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    config(['tenancy.subdomains_enabled' => true, 'tenancy.base_domain' => 'menus.test', 'tenancy.custom_domains_enabled' => true]);
});

/** Controllable DNS: set `$dns->records` before verifying. */
function dmFakeDns(array $txt = [], array $cname = []): void
{
    app()->instance(DnsResolver::class, new class($txt, $cname) implements DnsResolver
    {
        public function __construct(private array $txt, private array $cname) {}

        public function txt(string $host): array
        {
            return $this->txt[$host] ?? [];
        }

        public function cname(string $host): array
        {
            return $this->cname[$host] ?? [];
        }
    });
}

function dmShop(array $features = ['custom_domain'], string $slug = 'dm'): array
{
    $r = Restaurant::create(['name' => 'Dom '.$slug, 'slug' => $slug.uniqid(), 'locale' => 'en', 'currency_code' => 'USD', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => array_fill_keys($features, true)]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $owner = User::factory()->create(['restaurant_id' => $r->id]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $owner->assignRole(Permissions::OWNER);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $r->forceFill(['owner_id' => $owner->id])->save();

    return [$r->fresh(), $owner];
}

describe('subdomain', function () {
    it('saves a clean subdomain and builds the public url from it', function () {
        [$r, $owner] = dmShop();
        $this->actingAs($owner)->put(route('domains.subdomain'), ['subdomain' => ' Bella-Pizza '])->assertSessionHasNoErrors();
        expect($r->fresh()->subdomain)->toBe('bella-pizza')->and($r->fresh()->publicUrl())->toContain('bella-pizza.menus.test');
    });

    it('rejects reserved, malformed and taken names', function () {
        [, $owner] = dmShop();
        [$other] = dmShop(slug: 'other');
        $other->forceFill(['subdomain' => 'taken-one'])->save();

        foreach (['www', 'a', '-bad-', 'has space', 'under_score'] as $bad) {
            $this->actingAs($owner)->put(route('domains.subdomain'), ['subdomain' => $bad])->assertSessionHasErrors('subdomain');
        }
        $this->actingAs($owner)->put(route('domains.subdomain'), ['subdomain' => 'taken-one'])->assertSessionHasErrors('subdomain');
        expect($owner->restaurant->fresh()->subdomain)->toBeNull();
    });

    it('can be cleared and is refused when the platform has subdomains off', function () {
        [$r, $owner] = dmShop();
        $r->forceFill(['subdomain' => 'abc'])->save();
        $this->actingAs($owner)->put(route('domains.subdomain'), ['subdomain' => ''])->assertSessionHasNoErrors();
        expect($r->fresh()->subdomain)->toBeNull();

        config(['tenancy.subdomains_enabled' => false]);
        $this->actingAs($owner)->put(route('domains.subdomain'), ['subdomain' => 'nope'])->assertSessionHasErrors('subdomain');
    });
});

describe('custom domain', function () {
    it('normalises a pasted url and starts unverified', function () {
        [$r, $owner] = dmShop();
        $this->actingAs($owner)->put(route('domains.custom'), ['custom_domain' => 'HTTPS://Menu.Example.com/some/path'])->assertSessionHasNoErrors();
        expect($r->fresh()->custom_domain)->toBe('menu.example.com')->and($r->fresh()->domain_verified_at)->toBeNull();
    });

    it('needs the plan feature and the platform flag', function () {
        [, $owner] = dmShop([]);
        $this->actingAs($owner)->put(route('domains.custom'), ['custom_domain' => 'menu.example.com'])->assertSessionHasErrors('custom_domain');
        $this->actingAs($owner)->get(route('domains.index'))->assertOk()->assertSee(__('domains.custom_upgrade'));

        [, $owner2] = dmShop(slug: 'x');
        config(['tenancy.custom_domains_enabled' => false]);
        $this->actingAs($owner2)->put(route('domains.custom'), ['custom_domain' => 'menu.example.com'])->assertSessionHasErrors('custom_domain');
    });

    it('rejects invalid hosts, platform domains and duplicates', function () {
        [, $owner] = dmShop();
        [$other] = dmShop(slug: 'o');
        $other->forceFill(['custom_domain' => 'used.example.com'])->save();

        foreach (['not a domain', 'localhost', 'menus.test', 'x.menus.test', 'used.example.com'] as $bad) {
            $this->actingAs($owner)->put(route('domains.custom'), ['custom_domain' => $bad])->assertSessionHasErrors('custom_domain');
        }
    });

    it('verifies through the TXT record and resets when the domain changes', function () {
        [$r, $owner] = dmShop();
        $this->actingAs($owner)->put(route('domains.custom'), ['custom_domain' => 'menu.example.com']);
        $r = $r->fresh();
        $service = app(DomainService::class);

        $owner->unsetRelations(); // a real request loads the restaurant afresh
        dmFakeDns();
        $this->actingAs($owner)->post(route('domains.verify'))->assertSessionHasErrors('custom_domain');
        expect($r->fresh()->domain_verified_at)->toBeNull();

        dmFakeDns(txt: ['_qrmenu-verify.menu.example.com' => ['something-else']]);
        expect(app(DomainService::class)->verify($r))->toBeFalse();

        dmFakeDns(txt: ['_qrmenu-verify.menu.example.com' => [$service->verificationToken($r)]]);
        $owner->unsetRelations();
        $this->actingAs($owner)->post(route('domains.verify'));
        expect($r->fresh()->domain_verified_at)->not->toBeNull()->and($r->fresh()->publicUrl())->toContain('menu.example.com');

        $this->actingAs($owner)->put(route('domains.custom'), ['custom_domain' => 'other.example.com']);
        expect($r->fresh()->domain_verified_at)->toBeNull();
    });

    it('does not accept another restaurant’s token', function () {
        [$a] = dmShop(slug: 'a');
        [$b] = dmShop(slug: 'b');
        $service = app(DomainService::class);
        $service->setCustomDomain($a, 'one.example.com');
        $service->setCustomDomain($b, 'two.example.com');
        dmFakeDns(txt: ['_qrmenu-verify.two.example.com' => [app(DomainService::class)->verificationToken($a->fresh())]]);

        expect(app(DomainService::class)->verify($b->fresh()))->toBeFalse();
    });

    it('also accepts a CNAME to the platform domain', function () {
        [$r] = dmShop();
        app(DomainService::class)->setCustomDomain($r, 'menu.example.com');
        dmFakeDns(cname: ['menu.example.com' => ['menus.test']]);

        expect(app(DomainService::class)->verify($r->fresh()))->toBeTrue();
    });

    it('serves the menu on a verified custom domain only', function () {
        [$r] = dmShop();
        app(DomainService::class)->setCustomDomain($r, 'menu.example.com');

        // Unverified: the host is not tenant-resolved, so the restaurant's menu is not shown.
        $this->get('http://menu.example.com/')->assertDontSee($r->name);
        $r->forceFill(['domain_verified_at' => now()])->save();
        $this->get('http://menu.example.com/')->assertOk()->assertSee($r->name);
    });
});

describe('access', function () {
    it('keeps non-managers out and shows the screen to the owner', function () {
        [$r, $owner] = dmShop();
        $this->actingAs($owner)->get(route('domains.index'))->assertOk()->assertSee('menus.test');

        $waiter = User::factory()->create(['restaurant_id' => $r->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
        $waiter->assignRole('waiter');
        app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
        $this->actingAs($waiter)->get(route('domains.index'))->assertForbidden();
        $this->actingAs($waiter)->put(route('domains.subdomain'), ['subdomain' => 'hack'])->assertForbidden();
    });
});

describe('platform admin', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['restaurant_id' => null]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
        $this->admin->assignRole(Permissions::SUPER_ADMIN);
    });

    it('sets addresses regardless of plan and verifies by hand', function () {
        [$r] = dmShop([]);
        $this->actingAs($this->admin)->put(route('admin.restaurants.domain', $r->id), ['subdomain' => 'vip', 'custom_domain' => 'vip.example.com', 'verified' => '1'])->assertSessionHasNoErrors();
        expect($r->fresh()->subdomain)->toBe('vip')->and($r->fresh()->custom_domain)->toBe('vip.example.com')->and($r->fresh()->domain_verified_at)->not->toBeNull();

        $this->actingAs($this->admin)->put(route('admin.restaurants.domain', $r->id), ['subdomain' => 'vip', 'custom_domain' => 'vip.example.com'])->assertSessionHasNoErrors();
        expect($r->fresh()->domain_verified_at)->toBeNull();
    });

    it('shows validation errors and can run the DNS check', function () {
        [$r] = dmShop();
        $this->actingAs($this->admin)->put(route('admin.restaurants.domain', $r->id), ['subdomain' => 'admin'])->assertSessionHasErrors('domain');

        app(DomainService::class)->setCustomDomain($r, 'menu.example.com');
        dmFakeDns(txt: ['_qrmenu-verify.menu.example.com' => [app(DomainService::class)->verificationToken($r->fresh())]]);
        $this->actingAs($this->admin)->post(route('admin.restaurants.domain.verify', $r->id))->assertSessionHasNoErrors();
        expect($r->fresh()->domain_verified_at)->not->toBeNull();
        $this->actingAs($this->admin)->get(route('admin.restaurants.show', $r->id))->assertOk()->assertSee('menu.example.com');
    });

    it('is closed to restaurant owners', function () {
        [$r, $owner] = dmShop();
        $this->actingAs($owner)->put(route('admin.restaurants.domain', $r->id), ['subdomain' => 'x1x'])->assertForbidden();
    });
});
