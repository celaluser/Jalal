<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\OptionGroup;
use App\Modules\Menu\Models\Product;
use App\Modules\Storefront\Services\CartPricing;
use App\Modules\Storefront\Services\MenuCache;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_active' => true, 'is_default' => true]);
    Language::firstOrCreate(['code' => 'tr'], ['name' => 'Turkish', 'native_name' => 'Türkçe', 'is_active' => true]);
    Language::firstOrCreate(['code' => 'ar'], ['name' => 'Arabic', 'native_name' => 'العربية', 'is_active' => true, 'is_rtl' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    config(['tenancy.central_domains' => ['localhost', '127.0.0.1']]);
});

function sfShop(array $attributes = [], bool $subscribed = true): Restaurant
{
    $r = Restaurant::create(array_merge(['name' => 'Bella', 'slug' => 'bella'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en', 'tr'], 'currency_code' => 'USD', 'onboarded_at' => now()], $attributes));

    if ($subscribed) {
        $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
        app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    }

    return $r;
}

function sfIn(Restaurant $r, Closure $fn): mixed
{
    return app(TenantContext::class)->runAs($r, $fn);
}

function sfProduct(Restaurant $r, string $name = 'Margherita', float $price = 12.0, array $extra = [], ?Category $category = null): Product
{
    return sfIn($r, function () use ($name, $price, $extra, $category) {
        $category ??= Category::first() ?? Category::create(['name' => ['en' => 'Pizza', 'tr' => 'Pizzalar'], 'sort' => 1]);

        return Product::create(array_merge(['category_id' => $category->id, 'name' => ['en' => $name, 'tr' => $name.' TR'], 'price' => $price, 'sort' => 1], $extra));
    });
}

function sfGroup(Restaurant $r, Product $p, array $group = [], array $options = [['Regular', 0], ['Large', 3]]): OptionGroup
{
    return sfIn($r, function () use ($p, $group, $options) {
        $g = OptionGroup::create(array_merge(['name' => ['en' => 'Size'], 'type' => 'single'], $group));
        foreach ($options as $i => [$name, $delta]) {
            $g->options()->create(['name' => ['en' => $name], 'price_delta' => $delta, 'sort' => $i + 1]);
        }
        $p->optionGroups()->attach($g->id, ['sort' => 0]);

        return $g->load('options');
    });
}

describe('public menu page', function () {
    it('shows the menu of the restaurant with its theme and without anyone elses', function () {
        $a = sfShop(['name' => 'Alpha Cafe']);
        $b = sfShop(['name' => 'Beta Bistro']);
        sfProduct($a, 'Alpha Pizza');
        sfProduct($b, 'Beta Burger');

        $html = $this->get('/r/'.$a->slug)->assertOk()->assertSee('Alpha Cafe')->assertSee('Alpha Pizza')->assertDontSee('Beta Burger')->assertDontSee('Beta Bistro')->getContent();

        expect($html)->toContain('--menu-accent:#ffb020')->toContain('--menu-bg:');
    });

    it('404s for unknown and suspended restaurants', function () {
        $r = sfShop(['status' => 'suspended']);

        $this->get('/r/does-not-exist')->assertNotFound();
        $this->get('/r/'.$r->slug)->assertNotFound();
    });

    it('goes offline politely when the restaurant has no active plan', function () {
        $r = sfShop(subscribed: false);
        sfProduct($r, 'Hidden Dish');

        $this->get('/r/'.$r->slug)->assertStatus(503)->assertHeader('Retry-After')->assertSee(__('customer.unavailable_title'))->assertDontSee('Hidden Dish');
        $this->postJson('/r/'.$r->slug.'/cart/quote', ['lines' => [['product_id' => 1, 'qty' => 1]]])->assertStatus(503);
    });

    it('lists only active, visible items and marks sold out ones', function () {
        $r = sfShop();
        sfProduct($r, 'Visible', 5);
        sfProduct($r, 'Secret', 5, ['is_active' => false]);
        sfProduct($r, 'Gone Today', 5, ['is_available' => false]);
        sfIn($r, fn () => Category::create(['name' => ['en' => 'Empty'], 'sort' => 9]));
        sfIn($r, fn () => Category::create(['name' => ['en' => 'Hidden cat'], 'sort' => 8, 'is_active' => false]));

        $html = $this->get('/r/'.$r->slug)->assertOk()->assertSee('Visible')->assertSee('Gone Today')->assertDontSee('Secret')->getContent();

        expect($html)->not->toContain('Empty')->not->toContain('Hidden cat');
    });

    it('is readable without javascript', function () {
        $r = sfShop();
        sfProduct($r, 'Plain Soup', 4.5, ['description' => ['en' => 'Hot and simple']]);

        $html = $this->get('/r/'.$r->slug)->getContent();

        expect($html)->toContain('<noscript>')->and(substr($html, strpos($html, '<noscript>')))->toContain('Plain Soup')->toContain('$4.50')->toContain('Hot and simple');
    });

    it('applies the chosen layout', function () {
        $r = sfShop(['theme' => 'fresh']);
        sfProduct($r);

        $this->get('/r/'.$r->slug)->assertSee('grid grid-cols-2 gap-3', false);
    });

    it('hides the credit line only when the plan allows', function () {
        $r = sfShop();
        sfProduct($r);
        $r->update(['branding' => ['menu' => ['hide_credit' => true]]]);

        $this->get('/r/'.$r->slug)->assertSee(__('customer.powered_by', ['name' => config('app.name')]));

        $plan = Plan::create(['name' => 'NoBrand', 'slug' => 'nb'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'features' => ['remove_branding' => true]]);
        app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());

        $this->get('/r/'.$r->slug)->assertDontSee(__('customer.powered_by', ['name' => config('app.name')]));
    });

    it('never puts markup from menu text into the page', function () {
        $r = sfShop();
        sfProduct($r, '<script>alert(1)</script>', 5, ['description' => ['en' => '</script><img src=x onerror=alert(2)>']]);

        $html = $this->get('/r/'.$r->slug)->getContent();

        expect($html)->not->toContain('<script>alert(1)</script>')->not->toContain('<img src=x onerror');
    });
});

describe('language', function () {
    it('shows the requested language and remembers it', function () {
        $r = sfShop();
        sfProduct($r, 'Margherita');

        $this->withHeader('Accept-Language', 'en')->get('/r/'.$r->slug)->assertSee('Margherita')->assertDontSee('Margherita TR');
        $response = $this->get('/r/'.$r->slug.'?lang=tr')->assertSee('Margherita TR')->assertSee('Pizzalar')->assertCookie('menu_lang', 'tr');
        expect($response->getContent())->toContain('lang="tr"');
    });

    it('ignores languages the restaurant does not offer', function () {
        $r = sfShop();
        sfProduct($r);

        $this->get('/r/'.$r->slug.'?lang=ar')->assertOk()->assertSee('lang="en"', false)->assertCookieMissing('menu_lang');
        $this->get('/r/'.$r->slug.'?lang=xx')->assertOk()->assertSee('lang="en"', false);
    });

    it('follows the browser language, then the restaurant default', function () {
        $r = sfShop(['locale' => 'en']);
        sfProduct($r);

        $this->withHeader('Accept-Language', 'tr-TR,tr;q=0.9,en;q=0.5')->get('/r/'.$r->slug)->assertSee('lang="tr"', false);
        $this->withHeader('Accept-Language', 'fr-FR')->get('/r/'.$r->slug)->assertSee('lang="en"', false);
    });

    it('switches direction for right-to-left languages', function () {
        $r = sfShop(['locale' => 'ar', 'menu_locales' => ['ar', 'en']]);
        sfProduct($r);

        $this->withHeader('Accept-Language', 'ar')->get('/r/'.$r->slug)->assertSee('dir="rtl"', false);
        $this->get('/r/'.$r->slug.'?lang=en')->assertSee('dir="ltr"', false);
    });
});

describe('table entry', function () {
    it('remembers the table from a scanned code and opens the menu', function () {
        $r = sfShop();
        sfProduct($r);
        $t = sfIn($r, fn () => DiningTable::create(['name' => 'Table 5']));

        $this->get('/r/'.$r->slug.'/t/'.$t->token)->assertRedirect(url('/r/'.$r->slug));
        $page = $this->get('/r/'.$r->slug)->assertOk()->assertSee(__('customer.table', ['name' => 'Table 5']));

        expect($page->getContent())->toContain('noindex');
    });

    it('does not index or remember anything for the plain menu', function () {
        $r = sfShop();
        sfProduct($r);

        expect($this->get('/r/'.$r->slug)->getContent())->not->toContain('noindex')->not->toContain(__('customer.table', ['name' => '']));
    });

    it('keeps the language through the redirect', function () {
        $r = sfShop();
        $t = sfIn($r, fn () => DiningTable::create(['name' => 'T1']));

        $this->get('/r/'.$r->slug.'/t/'.$t->token.'?lang=tr')->assertRedirect(url('/r/'.$r->slug.'?lang=tr'));
    });

    it('ignores unknown, inactive and foreign tokens', function () {
        $a = sfShop();
        $b = sfShop();
        sfProduct($a);
        $mine = sfIn($a, fn () => DiningTable::create(['name' => 'Mine', 'is_active' => false]));
        $theirs = sfIn($b, fn () => DiningTable::create(['name' => 'Theirs']));

        foreach ([$mine->token, $theirs->token, 'abcdefabcdef'] as $token) {
            $this->get('/r/'.$a->slug.'/t/'.$token)->assertRedirect()->assertSessionHas('table_invalid');
            $this->get('/r/'.$a->slug)->assertDontSee('Theirs')->assertDontSee('Mine');
            $this->flushSession();
        }
    });

    it('forgets a table that has been switched off since', function () {
        $r = sfShop();
        sfProduct($r);
        $t = sfIn($r, fn () => DiningTable::create(['name' => 'T9']));
        $this->get('/r/'.$r->slug.'/t/'.$t->token);

        sfIn($r, fn () => $t->update(['is_active' => false]));

        $this->get('/r/'.$r->slug)->assertDontSee(__('customer.table', ['name' => 'T9']));
    });

    it('rejects malformed tokens before touching the database', function () {
        $r = sfShop();

        $this->get('/r/'.$r->slug.'/t/AB')->assertNotFound();
        $this->get('/r/'.$r->slug.'/t/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/r/'.$r->slug.'/t/abc-def-ghi')->assertNotFound();
    });
});

describe('restaurant domains', function () {
    it('serves the menu at the root of a verified custom domain and keeps the landing page elsewhere', function () {
        config(['tenancy.custom_domains_enabled' => true]);
        $r = sfShop(['name' => 'Domain Diner', 'custom_domain' => 'menu.diner.test', 'domain_verified_at' => now()]);
        sfProduct($r, 'Domain Dish');

        $this->get('http://menu.diner.test/')->assertOk()->assertSee('Domain Diner')->assertSee('Domain Dish');
        $this->get('http://localhost/')->assertOk()->assertDontSee('Domain Dish');
    });

    it('serves the menu on a subdomain, with table links and quotes working on that host', function () {
        config(['tenancy.subdomains_enabled' => true, 'tenancy.base_domain' => 'qrmenu.test']);
        $r = sfShop(['name' => 'Sub Place', 'subdomain' => 'subplace']);
        $p = sfProduct($r, 'Sub Dish', 8);
        $t = sfIn($r, fn () => DiningTable::create(['name' => 'S1']));

        $this->get('http://subplace.qrmenu.test/')->assertOk()->assertSee('Sub Dish');
        $this->get('http://subplace.qrmenu.test/t/'.$t->token)->assertRedirect('http://subplace.qrmenu.test');
        $this->get('http://subplace.qrmenu.test/')->assertSee(__('customer.table', ['name' => 'S1']));
        $this->postJson('http://subplace.qrmenu.test/cart/quote', ['lines' => [['product_id' => $p->id, 'qty' => 2]]])->assertOk()->assertJsonPath('subtotal_cents', 1600);
    });

    it('does not resolve a restaurant on the platform domain, nor an unverified custom domain', function () {
        config(['tenancy.custom_domains_enabled' => true]);
        sfShop(['name' => 'Pending Pizzeria', 'custom_domain' => 'pending.test', 'domain_verified_at' => null]);

        $this->get('http://localhost/t/abcdefabcdef')->assertNotFound();
        $this->postJson('http://localhost/cart/quote', ['lines' => []])->assertNotFound();
        $this->get('http://pending.test/')->assertOk()->assertDontSee('Pending Pizzeria');
    });

    it('does not serve a suspended restaurants domain', function () {
        config(['tenancy.custom_domains_enabled' => true]);
        sfShop(['name' => 'Closed Co', 'custom_domain' => 'closed.test', 'domain_verified_at' => now(), 'status' => 'suspended']);

        $this->get('http://closed.test/')->assertNotFound();
    });
});

describe('menu cache', function () {
    it('serves from cache and refreshes after any menu change', function () {
        $r = sfShop();
        $p = sfProduct($r, 'Old Name');
        $this->get('/r/'.$r->slug)->assertSee('Old Name');

        // A change made through the models invalidates the cache.
        sfIn($r, fn () => $p->update(['name' => ['en' => 'New Name']]));
        $this->get('/r/'.$r->slug)->assertSee('New Name')->assertDontSee('Old Name');

        // A raw query change is invisible until something bumps the version: proof the cache is used.
        DB::table('products')->where('id', $p->id)->update(['name' => json_encode(['en' => 'Sneaky'])]);
        $this->get('/r/'.$r->slug)->assertSee('New Name')->assertDontSee('Sneaky');

        MenuCache::bump($r->id);
        $this->get('/r/'.$r->slug)->assertSee('Sneaky');
    });

    it('refreshes after deleting, hiding, reordering and changing options', function () {
        $r = sfShop();
        $owner = User::factory()->create(['restaurant_id' => $r->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
        $owner->assignRole(Permissions::OWNER);
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);
        $a = sfProduct($r, 'Aaa', 1, ['sort' => 1]);
        $b = sfProduct($r, 'Bbb', 1, ['sort' => 2]);
        $group = sfGroup($r, $a);

        $order = fn (string $html) => strpos($html, 'Aaa') < strpos($html, 'Bbb');
        expect($order($this->get('/r/'.$r->slug)->getContent()))->toBeTrue();

        $this->actingAs($owner)->postJson(route('menu.reorder', 'products'), ['ids' => [$b->id, $a->id]]);
        $html = $this->get('/r/'.$r->slug)->getContent();
        expect(strpos($html, 'Bbb') < strpos($html, 'Aaa'))->toBeTrue();

        $this->post(route('menu.products.toggle', $b), ['field' => 'is_active']);
        $this->get('/r/'.$r->slug)->assertDontSee('Bbb');

        $this->delete(route('menu.option-groups.destroy', $group->id));
        expect($this->get('/r/'.$r->slug)->getContent())->not->toContain('"Size"');

        $this->delete(route('menu.products.destroy', $a));
        $this->get('/r/'.$r->slug)->assertDontSee('Aaa');
    });

    it('keeps languages and restaurants apart', function () {
        $a = sfShop();
        $b = sfShop();
        sfProduct($a, 'Alpha');
        sfProduct($b, 'Bravo');

        $this->get('/r/'.$a->slug)->assertSee('Alpha');
        $this->get('/r/'.$b->slug)->assertSee('Bravo')->assertDontSee('Alpha');
        $this->get('/r/'.$a->slug.'?lang=tr')->assertSee('Alpha TR');
    });
});

describe('server-side cart pricing', function () {
    function quote($test, Restaurant $r, array $lines)
    {
        return $test->postJson('/r/'.$r->slug.'/cart/quote', ['lines' => $lines]);
    }

    it('prices lines with options and quantities from the database', function () {
        $r = sfShop();
        $p = sfProduct($r, 'Margherita', 12);
        $g = sfGroup($r, $p, [], [['Regular', 0], ['Large', 3.5]]);
        $large = $g->options[1];

        $res = quote($this, $r, [['product_id' => $p->id, 'qty' => 2, 'options' => [$large->id], 'note' => 'no basil']])->assertOk();

        $res->assertJsonPath('valid', true)->assertJsonPath('lines.0.unit_cents', 1550)->assertJsonPath('lines.0.total_cents', 3100)
            ->assertJsonPath('lines.0.total', '$31.00')->assertJsonPath('subtotal', '$31.00')->assertJsonPath('subtotal_cents', 3100)
            ->assertJsonPath('lines.0.name', 'Margherita')->assertJsonPath('lines.0.options.0.name', 'Large')->assertJsonPath('lines.0.note', 'no basil');
    });

    it('ignores any price or name the browser sends', function () {
        $r = sfShop();
        $p = sfProduct($r, 'Real Name', 10);

        $res = quote($this, $r, [['product_id' => $p->id, 'qty' => 1, 'price' => 0.01, 'unit_cents' => 1, 'name' => 'Fake', 'total_cents' => 1]])->assertOk();

        $res->assertJsonPath('lines.0.unit_cents', 1000)->assertJsonPath('lines.0.name', 'Real Name');
    });

    it('rejects unavailable, hidden, sold out and foreign products', function () {
        $a = sfShop();
        $b = sfShop();
        $ok = sfProduct($a, 'Ok', 5);
        $hidden = sfProduct($a, 'Hidden', 5, ['is_active' => false]);
        $sold = sfProduct($a, 'Sold', 5, ['is_available' => false]);
        $foreign = sfProduct($b, 'Foreign', 5);
        $inactiveCat = sfProduct($a, 'InCat', 5, [], sfIn($a, fn () => Category::create(['name' => ['en' => 'Off'], 'is_active' => false])));

        $res = quote($this, $a, array_map(fn ($p) => ['product_id' => $p->id, 'qty' => 1], [$ok, $hidden, $sold, $foreign, $inactiveCat]))->assertOk();

        $res->assertJsonPath('valid', false)->assertJsonPath('lines.0.errors', [])->assertJsonPath('lines.1.errors.0', 'unavailable')
            ->assertJsonPath('lines.2.errors.0', 'sold_out')->assertJsonPath('lines.3.errors.0', 'unavailable')->assertJsonPath('lines.4.errors.0', 'unavailable')
            ->assertJsonPath('subtotal_cents', 500); // only the valid line counts
    });

    it('enforces option rules', function () {
        $r = sfShop();
        $p = sfProduct($r, 'Pizza', 10);
        $other = sfProduct($r, 'Other', 10);
        $size = sfGroup($r, $p, ['is_required' => true]);
        $extras = sfGroup($r, $p, ['name' => ['en' => 'Extras'], 'type' => 'multiple', 'max_select' => 2], [['Cheese', 1], ['Olives', 1], ['Ham', 2]]);
        $foreignGroup = sfGroup($r, $other, [], [['Alien', 5]]);
        $off = sfIn($r, fn () => tap($extras->options[2])->update(['is_available' => false]));

        $line = fn (array $options) => ['product_id' => $p->id, 'qty' => 1, 'options' => $options];
        $errors = fn (array $options) => quote($this, $r, [$line($options)])->json('lines.0.errors');

        expect($errors([$size->options[0]->id]))->toBe([])                                                           // required satisfied
            ->and($errors([]))->toContain('option_required')                                                          // required missing
            ->and($errors([$size->options[0]->id, $size->options[1]->id]))->toContain('too_many_options')            // single group, two picks
            ->and($errors([$size->options[0]->id, ...$extras->options->take(2)->pluck('id')->all()]))->toBe([])     // two of max two
            ->and($errors([$size->options[0]->id, $extras->options[0]->id, $extras->options[1]->id, $extras->options[2]->id]))->toContain('too_many_options')
            ->and($errors([$size->options[0]->id, $off->id]))->toContain('option_unavailable')
            ->and($errors([$size->options[0]->id, $foreignGroup->options[0]->id]))->toContain('invalid_option')      // another product's option
            ->and($errors([$size->options[0]->id, 999999]))->toContain('invalid_option');
    });

    it('bounds quantity and survives garbage input', function () {
        $r = sfShop();
        $p = sfProduct($r, 'Pizza', 10);

        foreach ([0, -1, 51, 'abc', 1.5, [1]] as $qty) {
            expect(quote($this, $r, [['product_id' => $p->id, 'qty' => $qty]])->assertOk()->json('lines.0.errors'))->toContain('quantity');
        }

        quote($this, $r, [['product_id' => 'x', 'qty' => 1], 'junk', ['product_id' => [], 'qty' => 1, 'options' => 'nope']])->assertOk()->assertJsonPath('valid', false);
        $this->postJson('/r/'.$r->slug.'/cart/quote', [])->assertStatus(422);
        $this->postJson('/r/'.$r->slug.'/cart/quote', ['lines' => 'nope'])->assertStatus(422);
        $this->postJson('/r/'.$r->slug.'/cart/quote', ['lines' => array_fill(0, 51, ['product_id' => $p->id, 'qty' => 1])])->assertStatus(422);
    });

    it('does money in whole cents, without float drift', function () {
        $r = sfShop();
        $p = sfProduct($r, 'Cheap', 0.1);
        $g = sfGroup($r, $p, [], [['Tiny', 0.2]]);

        quote($this, $r, [['product_id' => $p->id, 'qty' => 3, 'options' => [$g->options[0]->id]]])->assertOk()->assertJsonPath('lines.0.unit_cents', 30)->assertJsonPath('subtotal_cents', 90);
    });

    it('never lets negative option prices push a price below zero', function () {
        $r = sfShop();
        $p = sfProduct($r, 'Discounted', 2);
        $g = sfGroup($r, $p, [], [['Coupon', -5]]);

        quote($this, $r, [['product_id' => $p->id, 'qty' => 1, 'options' => [$g->options[0]->id]]])->assertOk()->assertJsonPath('lines.0.unit_cents', 0);
    });

    it('strips markup from notes and caps their length', function () {
        $r = sfShop();
        $p = sfProduct($r);

        $note = quote($this, $r, [['product_id' => $p->id, 'qty' => 1, 'note' => '<b>extra</b> '.str_repeat('x', 500)]])->json('lines.0.note');

        expect($note)->toStartWith('extra xxx')->not->toContain('<')->and(mb_strlen($note))->toBe(200);
    });

    it('answers in the guests language', function () {
        $r = sfShop();
        $p = sfProduct($r, 'Margherita', 5);

        quote($this, $r, [['product_id' => $p->id, 'qty' => 1]])->assertJsonPath('lines.0.name', 'Margherita');
        $this->withHeader('Accept-Language', 'tr')->postJson('/r/'.$r->slug.'/cart/quote', ['lines' => [['product_id' => $p->id, 'qty' => 1]]])->assertJsonPath('lines.0.name', 'Margherita TR');
    });

    it('returns an empty, invalid quote for an empty cart', function () {
        $r = sfShop();

        $res = sfIn($r, fn () => app(CartPricing::class)->quote($r, []));
        expect($res)->toMatchArray(['lines' => [], 'subtotal_cents' => 0, 'valid' => false]);
    });
});
