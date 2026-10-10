<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Billing\Services\UsageReport;
use App\Modules\Billing\Support\UsageRegistry;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Option;
use App\Modules\Menu\Models\OptionGroup;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Services\MenuService;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    // The menu module registers usage counters at boot; other test files flush them.
    UsageRegistry::register('products', fn () => Product::count());
    UsageRegistry::register('categories', fn () => Category::count());
});

function menuShop(array $limits = [], string $locale = 'en', array $menuLocales = ['en', 'tr']): array
{
    $restaurant = Restaurant::create(['name' => 'Cafe', 'slug' => 'cafe'.uniqid(), 'locale' => $locale, 'menu_locales' => $menuLocales, 'onboarded_at' => now(), 'currency_code' => 'USD']);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => $limits, 'features' => []]);
    app(SubscriptionService::class)->assign($restaurant, $plan, now()->addMonth());

    $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($restaurant->id);
    $owner->assignRole(Permissions::OWNER);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $restaurant->update(['owner_id' => $owner->id]);

    return [$restaurant, $owner];
}

function inTenant(Restaurant $restaurant, Closure $callback): mixed
{
    return app(TenantContext::class)->runAs($restaurant, $callback);
}

function makeCategory(Restaurant $r, string $name = 'Mains', array $extra = []): Category
{
    return inTenant($r, fn () => Category::create(array_merge(['name' => ['en' => $name], 'sort' => 1, 'is_active' => true], $extra)));
}

function makeProduct(Restaurant $r, Category $c, string $name = 'Burger', array $extra = []): Product
{
    return inTenant($r, fn () => Product::create(array_merge(['category_id' => $c->id, 'name' => ['en' => $name], 'price' => 9.5, 'sort' => 1, 'is_active' => true, 'is_available' => true], $extra)));
}

function productPayload(Category $c, array $extra = []): array
{
    return array_merge(['category_id' => $c->id, 'name' => ['en' => 'Pizza', 'tr' => 'Pizza TR'], 'price' => '12.50', 'is_active' => 1, 'is_available' => 1], $extra);
}

describe('access and isolation', function () {
    it('is for staff with menu.manage only', function () {
        [$r, $owner] = menuShop();
        $waiter = User::factory()->create(['restaurant_id' => $r->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
        $waiter->assignRole(Permissions::WAITER);
        app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));

        $this->get(route('menu.index'))->assertRedirect(route('login'));
        $this->actingAs($waiter)->get(route('menu.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('menu.index'))->assertOk();
    });

    it('hides other restaurants menus on every route', function () {
        [$mine, $owner] = menuShop();
        [$theirs] = menuShop();
        $cat = makeCategory($theirs);
        $prod = makeProduct($theirs, $cat);
        $group = inTenant($theirs, fn () => OptionGroup::create(['name' => ['en' => 'Size'], 'type' => 'single']));

        $this->actingAs($owner);
        $this->get(route('menu.categories.edit', $cat->id))->assertNotFound();
        $this->put(route('menu.categories.update', $cat->id), ['name' => ['en' => 'x']])->assertNotFound();
        $this->delete(route('menu.categories.destroy', $cat->id))->assertNotFound();
        $this->get(route('menu.products.edit', $prod->id))->assertNotFound();
        $this->post(route('menu.products.toggle', $prod->id), ['field' => 'is_active'])->assertNotFound();
        $this->post(route('menu.products.duplicate', $prod->id))->assertNotFound();
        $this->delete(route('menu.products.destroy', $prod->id))->assertNotFound();
        $this->get(route('menu.option-groups.edit', $group->id))->assertNotFound();
        expect($prod->fresh()->is_active)->toBeTrue();
    });

    it('rejects foreign category and option group ids when saving a product', function () {
        [$mine, $owner] = menuShop();
        [$theirs] = menuShop();
        $foreignCat = makeCategory($theirs);
        $foreignGroup = inTenant($theirs, fn () => OptionGroup::create(['name' => ['en' => 'Size'], 'type' => 'single']));
        $ownCat = makeCategory($mine);

        $this->actingAs($owner)->post(route('menu.products.store'), productPayload($foreignCat))->assertSessionHasErrors('category_id');
        $this->post(route('menu.products.store'), productPayload($ownCat, ['option_groups' => [$foreignGroup->id]]))->assertSessionHasErrors('option_groups.0');
        expect(inTenant($mine, fn () => Product::count()))->toBe(0);
    });
});

describe('categories', function () {
    it('creates a translated category, requiring the default language only', function () {
        [$r, $owner] = menuShop();

        $this->actingAs($owner)->post(route('menu.categories.store'), ['name' => ['en' => '', 'tr' => 'Ana yemek']])->assertSessionHasErrors('name.en');
        $this->post(route('menu.categories.store'), ['name' => ['en' => ' Mains ', 'tr' => 'Ana yemek', 'de' => 'Hauptgang'], 'is_active' => 1])->assertRedirect();

        $c = inTenant($r, fn () => Category::first());
        expect($c->name)->toBe(['en' => 'Mains', 'tr' => 'Ana yemek'])->and($c->is_active)->toBeTrue()->and($c->tr('name', 'tr'))->toBe('Ana yemek');
    });

    it('updates with a photo and refuses deleting a category that has products', function () {
        [$r, $owner] = menuShop();
        $c = makeCategory($r);
        makeProduct($r, $c);

        $this->actingAs($owner)->put(route('menu.categories.update', $c), ['name' => ['en' => 'Big plates'], 'image' => UploadedFile::fake()->image('c.jpg', 400, 300)])->assertRedirect();
        $c = inTenant($r, fn () => $c->fresh()->load('image'));
        expect($c->tr('name'))->toBe('Big plates')->and($c->is_active)->toBeFalse()->and($c->image)->not->toBeNull();

        $this->delete(route('menu.categories.destroy', $c))->assertSessionHasErrors('category');
        expect(inTenant($r, fn () => Category::count()))->toBe(1);

        inTenant($r, fn () => Product::query()->delete());
        $this->delete(route('menu.categories.destroy', $c))->assertRedirect(route('menu.index'));
        expect(inTenant($r, fn () => Category::count()))->toBe(0);
    });
});

describe('products', function () {
    it('creates a full product with allergens, dietary labels and option groups', function () {
        [$r, $owner] = menuShop();
        $c = makeCategory($r);
        $g = inTenant($r, fn () => OptionGroup::create(['name' => ['en' => 'Size'], 'type' => 'single']));

        $this->actingAs($owner)->post(route('menu.products.store'), productPayload($c, [
            'description' => ['en' => 'Wood fired'], 'compare_price' => '15', 'calories' => 800, 'prep_minutes' => 12,
            'allergens' => ['gluten', 'milk'], 'dietary' => ['vegetarian'], 'option_groups' => [$g->id], 'is_featured' => 1,
            'image' => UploadedFile::fake()->image('p.png', 800, 600),
        ]))->assertRedirect(route('menu.index', ['category' => $c->id]));

        $p = inTenant($r, fn () => Product::with(['image', 'optionGroups'])->first());
        expect($p->tr('name', 'tr'))->toBe('Pizza TR')->and((float) $p->price)->toBe(12.5)->and($p->isOnSale())->toBeTrue()
            ->and($p->allergens)->toBe(['gluten', 'milk'])->and($p->dietary)->toBe(['vegetarian'])->and($p->is_featured)->toBeTrue()
            ->and($p->optionGroups->pluck('id')->all())->toBe([$g->id])->and($p->image)->not->toBeNull();
        Storage::disk('public')->assertExists($p->image->path);
    });

    it('validates input', function () {
        [$r, $owner] = menuShop();
        $c = makeCategory($r);

        $this->actingAs($owner)->post(route('menu.products.store'), productPayload($c, [
            'name' => ['en' => ''], 'price' => '-1', 'allergens' => ['kryptonite'], 'dietary' => ['carnivore'], 'calories' => 'lots',
            'image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasErrors(['name.en', 'price', 'allergens.0', 'dietary.0', 'calories', 'image']);
    });

    it('needs a category first', function () {
        [$r, $owner] = menuShop();

        $this->actingAs($owner)->get(route('menu.products.create'))->assertRedirect(route('menu.categories.create'));
    });

    it('updates, replaces and removes the photo, and moves between categories', function () {
        [$r, $owner] = menuShop();
        $a = makeCategory($r, 'A');
        $b = makeCategory($r, 'B', ['sort' => 2]);
        $p = makeProduct($r, $a);

        $this->actingAs($owner)->put(route('menu.products.update', $p), productPayload($b, ['image' => UploadedFile::fake()->image('1.png')]))->assertRedirect();
        $p = inTenant($r, fn () => $p->fresh()->load('image'));
        $first = $p->image->path;
        expect($p->category_id)->toBe($b->id)->and($p->is_featured)->toBeFalse();
        Storage::disk('public')->assertExists($first);

        $this->put(route('menu.products.update', $p), productPayload($b, ['image' => UploadedFile::fake()->image('2.png')]));
        Storage::disk('public')->assertMissing($first);

        $this->put(route('menu.products.update', $p), productPayload($b, ['remove_image' => 1]));
        expect(inTenant($r, fn () => $p->fresh()->image_media_id))->toBeNull();
    });

    it('toggles only whitelisted switches', function () {
        [$r, $owner] = menuShop();
        $p = makeProduct($r, makeCategory($r));

        $this->actingAs($owner)->post(route('menu.products.toggle', $p), ['field' => 'is_available'])->assertRedirect();
        expect(inTenant($r, fn () => $p->fresh()->is_available))->toBeFalse();

        $this->post(route('menu.products.toggle', $p), ['field' => 'price'])->assertSessionHasErrors('field');
        $this->post(route('menu.products.toggle', $p), ['field' => 'restaurant_id'])->assertSessionHasErrors('field');
    });

    it('duplicates a product as a hidden copy right after the original, with its option groups', function () {
        [$r, $owner] = menuShop();
        $c = makeCategory($r);
        $g = inTenant($r, fn () => OptionGroup::create(['name' => ['en' => 'Size'], 'type' => 'single']));
        $a = makeProduct($r, $c, 'A', ['sort' => 1]);
        $b = makeProduct($r, $c, 'B', ['sort' => 2]);
        inTenant($r, fn () => $a->optionGroups()->attach($g->id, ['sort' => 0]));

        $this->actingAs($owner)->post(route('menu.products.duplicate', $a))->assertRedirect();

        $copy = inTenant($r, fn () => Product::where('name->en', 'A (copy)')->first());
        expect($copy->is_active)->toBeFalse()->and($copy->sort)->toBe(2)
            ->and(inTenant($r, fn () => $copy->optionGroups()->pluck('option_groups.id')->all()))->toBe([$g->id])
            ->and(inTenant($r, fn () => $b->fresh()->sort))->toBe(3);
    });

    it('deletes a product and its option links', function () {
        [$r, $owner] = menuShop();
        $c = makeCategory($r);
        $p = makeProduct($r, $c);
        $g = inTenant($r, fn () => OptionGroup::create(['name' => ['en' => 'Size'], 'type' => 'single']));
        inTenant($r, fn () => $p->optionGroups()->attach($g->id));

        $this->actingAs($owner)->delete(route('menu.products.destroy', $p))->assertRedirect();

        expect(inTenant($r, fn () => Product::count()))->toBe(0)->and(DB::table('option_group_product')->count())->toBe(0);
    });
});

describe('plan limits', function () {
    it('stops adding at the plan limit and counts usage', function () {
        [$r, $owner] = menuShop(['products' => 1, 'categories' => 1]);
        $c = makeCategory($r);

        $this->actingAs($owner)->post(route('menu.categories.store'), ['name' => ['en' => 'Second']])->assertSessionHasErrors('limit');
        $this->post(route('menu.products.store'), productPayload($c))->assertRedirect();
        $this->post(route('menu.products.store'), productPayload($c))->assertSessionHasErrors('limit');

        $p = inTenant($r, fn () => Product::first());
        $this->post(route('menu.products.duplicate', $p))->assertSessionHasErrors('limit');

        $rows = inTenant($r, fn () => collect(app(UsageReport::class)->for($r))->keyBy('key'));
        expect($rows['products'])->toMatchArray(['used' => 1, 'limit' => 1, 'state' => 'full'])->and($rows['categories']['state'])->toBe('full');
    });

    it('lets an unlimited plan keep adding', function () {
        [$r, $owner] = menuShop(['products' => null]);
        $c = makeCategory($r);

        foreach (range(1, 3) as $i) {
            $this->actingAs($owner)->post(route('menu.products.store'), productPayload($c, ['name' => ['en' => "P{$i}"]]))->assertRedirect();
        }

        expect(inTenant($r, fn () => Product::count()))->toBe(3);
    });
});

describe('option groups', function () {
    it('creates a group with options and edits it in place', function () {
        [$r, $owner] = menuShop();

        $this->actingAs($owner)->post(route('menu.option-groups.store'), [
            'name' => ['en' => 'Size', 'tr' => 'Boyut'], 'type' => 'single', 'is_required' => 1,
            'options' => [
                ['name' => ['en' => 'Small'], 'price_delta' => '0', 'is_available' => 1, 'is_default' => 1],
                ['name' => ['en' => 'Large'], 'price_delta' => '2.50', 'is_available' => 1, 'is_default' => 1],
            ],
        ])->assertRedirect();

        $g = inTenant($r, fn () => OptionGroup::with('options')->first());
        expect($g->is_required)->toBeTrue()->and($g->options)->toHaveCount(2)
            ->and($g->options->where('is_default', true))->toHaveCount(1) // single choice: one preselected option
            ->and((float) $g->options[1]->price_delta)->toBe(2.5);

        $keep = $g->options[0];
        $this->put(route('menu.option-groups.update', $g), [
            'name' => ['en' => 'Size'], 'type' => 'multiple', 'max_select' => 2,
            'options' => [['id' => $keep->id, 'name' => ['en' => 'Tiny'], 'price_delta' => '-1'], ['name' => ['en' => 'Huge'], 'price_delta' => '5', 'is_available' => 1]],
        ])->assertRedirect();

        $g = inTenant($r, fn () => $g->fresh()->load('options'));
        expect($g->type)->toBe('multiple')->and($g->max_select)->toBe(2)->and($g->options->pluck('id')->first())->toBe($keep->id)
            ->and($g->options->map->tr('name')->all())->toBe(['Tiny', 'Huge'])
            ->and($g->options[0]->is_available)->toBeFalse();
        expect(inTenant($r, fn () => Option::count()))->toBe(2);
    });

    it('requires a type, a name and at least one named option', function () {
        [$r, $owner] = menuShop();

        $this->actingAs($owner)->post(route('menu.option-groups.store'), ['name' => ['en' => ''], 'type' => 'x', 'options' => []])
            ->assertSessionHasErrors(['name.en', 'type', 'options']);
        $this->post(route('menu.option-groups.store'), ['name' => ['en' => 'A'], 'type' => 'single', 'options' => [['name' => ['en' => '']]]])
            ->assertSessionHasErrors('options.0.name.en');
    });

    it('does not let a forged option id touch another groups option', function () {
        [$r, $owner] = menuShop();
        $mine = inTenant($r, fn () => OptionGroup::create(['name' => ['en' => 'Mine'], 'type' => 'single']));
        $other = inTenant($r, fn () => OptionGroup::create(['name' => ['en' => 'Other'], 'type' => 'single']));
        $victim = inTenant($r, fn () => $other->options()->create(['name' => ['en' => 'Keep me'], 'price_delta' => 0, 'sort' => 1]));

        $this->actingAs($owner)->put(route('menu.option-groups.update', $mine), ['name' => ['en' => 'Mine'], 'type' => 'single', 'options' => [['id' => $victim->id, 'name' => ['en' => 'Hijacked']]]]);

        expect(inTenant($r, fn () => $victim->fresh()->tr('name')))->toBe('Keep me');
    });

    it('deletes a group with its options and product links', function () {
        [$r, $owner] = menuShop();
        $p = makeProduct($r, makeCategory($r));
        $g = inTenant($r, fn () => OptionGroup::create(['name' => ['en' => 'Size'], 'type' => 'single']));
        inTenant($r, fn () => $g->options()->create(['name' => ['en' => 'S'], 'sort' => 1]));
        inTenant($r, fn () => $p->optionGroups()->attach($g->id));

        $this->actingAs($owner)->delete(route('menu.option-groups.destroy', $g))->assertRedirect();

        expect(inTenant($r, fn () => OptionGroup::count()))->toBe(0)->and(inTenant($r, fn () => Option::count()))->toBe(0)->and(DB::table('option_group_product')->count())->toBe(0);
    });
});

describe('ordering', function () {
    it('saves a dragged order', function () {
        [$r, $owner] = menuShop();
        $c = makeCategory($r);
        [$a, $b, $d] = [makeProduct($r, $c, 'A', ['sort' => 1]), makeProduct($r, $c, 'B', ['sort' => 2]), makeProduct($r, $c, 'C', ['sort' => 3])];

        $this->actingAs($owner)->postJson(route('menu.reorder', 'products'), ['ids' => [$d->id, $a->id, $b->id]])->assertOk();

        expect(inTenant($r, fn () => Product::orderBy('sort')->pluck('name')->map(fn ($n) => $n['en'])->all()))->toBe(['C', 'A', 'B']);
    });

    it('ignores ids of other restaurants and unknown types', function () {
        [$r, $owner] = menuShop();
        [$other] = menuShop();
        $mine = makeCategory($r, 'Mine', ['sort' => 5]);
        $theirs = makeCategory($other, 'Theirs', ['sort' => 7]);

        $this->actingAs($owner)->postJson(route('menu.reorder', 'categories'), ['ids' => [$theirs->id, $mine->id]])->assertOk();

        expect(inTenant($other, fn () => $theirs->fresh()->sort))->toBe(7)->and(inTenant($r, fn () => $mine->fresh()->sort))->toBe(1);
        $this->postJson(route('menu.reorder', 'users'), ['ids' => [1]])->assertNotFound();
        $this->postJson(route('menu.reorder', 'products'), ['ids' => 'nope'])->assertStatus(422);
    });
});

describe('customer menu tree', function () {
    it('lists only what customers may see, in the requested language with fallback', function () {
        [$r] = menuShop();
        $soups = makeCategory($r, 'Soups', ['name' => ['en' => 'Soups', 'tr' => 'Çorbalar'], 'sort' => 1]);
        $hidden = makeCategory($r, 'Hidden', ['is_active' => false, 'sort' => 2]);
        $empty = makeCategory($r, 'Empty', ['sort' => 3]);
        $drinks = makeCategory($r, 'Drinks', ['sort' => 4]);

        makeProduct($r, $soups, 'Lentil', ['name' => ['en' => 'Lentil', 'tr' => 'Mercimek'], 'sort' => 1, 'compare_price' => 12, 'price' => 8]);
        makeProduct($r, $soups, 'Secret', ['is_active' => false, 'sort' => 2]);
        makeProduct($r, $soups, 'Sold', ['is_available' => false, 'sort' => 3]);
        makeProduct($r, $hidden, 'Nope');
        makeProduct($r, $drinks, 'Cola', ['name' => ['en' => 'Cola'], 'allergens' => ['milk']]);

        $group = inTenant($r, fn () => OptionGroup::create(['name' => ['en' => 'Size'], 'type' => 'single', 'is_required' => true]));
        inTenant($r, fn () => $group->options()->create(['name' => ['en' => 'Small'], 'price_delta' => 0, 'sort' => 1]));
        inTenant($r, fn () => $group->options()->create(['name' => ['en' => 'Gone'], 'price_delta' => 1, 'sort' => 2, 'is_available' => false]));
        inTenant($r, fn () => Product::where('name->en', 'Cola')->first()->optionGroups()->attach($group->id));

        $tree = app(MenuService::class)->tree($r, 'tr');

        expect(collect($tree)->pluck('name')->all())->toBe(['Çorbalar', 'Drinks'])
            ->and(collect($tree[0]['products'])->pluck('name')->all())->toBe(['Mercimek', 'Sold'])
            ->and($tree[0]['products'][0])->toMatchArray(['price' => 8.0, 'compare_price' => 12.0, 'available' => true])
            ->and($tree[0]['products'][1]['available'])->toBeFalse()
            ->and($tree[1]['products'][0]['name'])->toBe('Cola') // no Turkish text: falls back
            ->and($tree[1]['products'][0]['allergens'])->toBe(['milk'])
            ->and(collect($tree[1]['products'][0]['option_groups'][0]['options'])->pluck('name')->all())->toBe(['Small']);
    });

    it('never leaks another restaurant and works without a tenant in context', function () {
        [$a] = menuShop();
        [$b] = menuShop();
        makeProduct($a, makeCategory($a, 'A cat'), 'A dish');
        makeProduct($b, makeCategory($b, 'B cat'), 'B dish');

        app(TenantContext::class)->forget();
        $tree = app(MenuService::class)->tree($a);

        expect(collect($tree)->pluck('name')->all())->toBe(['A cat'])->and(app(TenantContext::class)->has())->toBeFalse();
    });
});

describe('translations and languages', function () {
    it('falls back to the default language and then to any text', function () {
        [$r] = menuShop(locale: 'tr', menuLocales: ['tr', 'en']);
        $c = inTenant($r, fn () => Category::create(['name' => ['tr' => 'Çorba', 'en' => 'Soup']]));
        $only = inTenant($r, fn () => Category::create(['name' => ['de' => 'Suppe']]));

        expect($c->tr('name', 'en'))->toBe('Soup')->and($c->tr('name', 'fr'))->toBe('Çorba')->and($only->tr('name', 'en'))->toBe('Suppe')
            ->and($only->tr('description'))->toBe('');
    });

    it('stores menu languages from the restaurant profile, always including the default', function () {
        Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true]);
        Language::firstOrCreate(['code' => 'tr'], ['name' => 'Turkish', 'is_active' => true]);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
        [$r, $owner] = menuShop(menuLocales: ['en']);

        $this->actingAs($owner)->put(route('restaurant.settings.profile'), ['name' => 'Cafe', 'timezone' => 'UTC', 'locale' => 'en', 'menu_locales' => ['tr']])->assertRedirect();

        expect($r->fresh()->menuLocales())->toBe(['en', 'tr']);

        $this->put(route('restaurant.settings.profile'), ['name' => 'Cafe', 'timezone' => 'UTC', 'locale' => 'tr', 'menu_locales' => ['zz']])->assertSessionHasErrors('menu_locales.0');
    });

    it('shows a language tab per menu language in the editor', function () {
        [$r, $owner] = menuShop();

        $this->actingAs($owner)->get(route('menu.categories.create'))->assertOk()->assertSee('name[en]', false)->assertSee('name[tr]', false);
    });

    it('formats prices with the restaurants currency', function () {
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
        [$r] = menuShop();

        expect($r->money(12.5))->toBe('$12.50');
        $r->currency_code = null;
        expect($r->money(12.5))->toBe('12.50');
    });
});

it('renders the workspace with categories, products and badges', function () {
    [$r, $owner] = menuShop(['products' => 10]);
    $c = makeCategory($r, 'Mains');
    makeProduct($r, $c, 'Steak', ['is_available' => false, 'is_featured' => true, 'price' => 20]);

    $this->actingAs($owner)->get(route('menu.index'))->assertOk()->assertSee('Mains')->assertSee('Steak')
        ->assertSee(__('menu.sold_out'))->assertSee(__('menu.featured'))->assertSee('1 of 10 products');
});
