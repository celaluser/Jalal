<?php

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

function seoShop(array $site = []): Restaurant
{
    $r = Restaurant::create(['name' => 'Seo Bistro', 'slug' => 'seo'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en', 'tr'], 'currency_code' => 'USD', 'onboarded_at' => now(), 'phone' => '+90 212 000 00 00', 'address' => '1 Main St', 'city' => 'Istanbul', 'site_settings' => $site]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => []]), now()->addMonth());

    return $r;
}

function seoUser(Restaurant $r, string $role): User
{
    $u = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $u->assignRole($role);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $u;
}

it('puts canonical, language alternates and restaurant structured data on the menu', function () {
    $r = seoShop(['seo_title' => ['en' => 'Best pizza in Istanbul'], 'seo_description' => ['en' => 'Wood-fired pizza.'], 'cuisine' => 'Italian', 'price_range' => '$$', 'hours' => [0 => '11:00-23:00', 1 => 'Closed']]);
    $html = $this->get("/r/{$r->slug}")->assertOk()->assertSee('<title>Best pizza in Istanbul</title>', false)->assertSee('name="description" content="Wood-fired pizza."', false)
        ->assertSee('rel="canonical"', false)->assertSee('hreflang="tr"', false)->assertDontSee('name="robots"', false)->getContent();

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    $ld = json_decode($m[1], true);
    expect($ld['@type'])->toBe('Restaurant')->and($ld['name'])->toBe('Seo Bistro')->and($ld['servesCuisine'])->toBe('Italian')->and($ld['priceRange'])->toBe('$$')
        ->and($ld['openingHours'])->toBe(['Mo 11:00-23:00'])->and($ld['address']['addressLocality'])->toBe('Istanbul');
});

it('tells search engines to stay away when the owner turns indexing off, and empties the sitemap', function () {
    $r = seoShop(['indexable' => false]);
    $this->get("/r/{$r->slug}")->assertOk()->assertSee('name="robots" content="noindex"', false)->assertDontSee('application/ld+json', false);
    expect($this->get("/r/{$r->slug}/sitemap.xml")->assertOk()->getContent())->not->toContain('<loc>');
});

it('lists the menu in every language and the About page in the sitemap, never table pages', function () {
    $r = seoShop(['site_enabled' => true]);
    $xml = $this->get("/r/{$r->slug}/sitemap.xml")->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8')->getContent();
    expect($xml)->toContain("/r/{$r->slug}</loc>")->toContain("/r/{$r->slug}/about</loc>")->toContain('hreflang="tr"')->not->toContain('/t/');
});

it('serves the About page only when published, with escaped text and no leaks from other languages', function () {
    $r = seoShop(['about' => ['en' => 'We bake <script>alert(1)</script> daily', 'tr' => 'Her gün pişiriyoruz'], 'hours' => [0 => '10:00-22:00']]);
    $this->get("/r/{$r->slug}/about")->assertNotFound();

    $r->update(['site_settings' => $r->site_settings + ['site_enabled' => true]]);
    $this->get("/r/{$r->slug}/about")->assertOk()->assertSee('We bake &lt;script&gt;', false)->assertDontSee('<script>alert(1)', false)->assertSee('10:00-22:00')->assertSee('tel:+902120000000', false)->assertSee('1 Main St');
    $this->get("/r/{$r->slug}/about?lang=tr")->assertOk()->assertSee('Her gün pişiriyoruz')->assertSee('Hakkımızda');
    $this->get("/r/{$r->slug}")->assertSee('/about', false);
});

it('is edited by marketing staff, validated, and kept per restaurant', function () {
    $r = seoShop();
    $other = seoShop();
    $owner = seoUser($r, 'restaurant_owner');
    $this->actingAs($owner)->get(route('site.edit'))->assertOk();
    $this->actingAs($owner)->put(route('site.update'), ['site_enabled' => '1', 'indexable' => '1', 'seo_title' => ['en' => 'Hello'], 'about' => ['en' => 'Story'], 'hours' => [0 => '11:00-23:00'], 'map_url' => 'https://maps.example.com/x', 'price_range' => '$$', 'cuisine' => 'Greek'])->assertSessionHasNoErrors();
    expect($r->fresh()->site_settings)->toMatchArray(['site_enabled' => true, 'cuisine' => 'Greek'])->and($other->fresh()->site_settings)->toBe([]);

    $this->actingAs($owner)->put(route('site.update'), ['map_url' => 'javascript:alert(1)'])->assertSessionHasErrors('map_url');
    $this->actingAs($owner)->put(route('site.update'), ['seo_title' => ['en' => str_repeat('a', 71)]])->assertSessionHasErrors('seo_title.en');
    $this->actingAs(seoUser($r, 'waiter'))->get(route('site.edit'))->assertForbidden();
});
