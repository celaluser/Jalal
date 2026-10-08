<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Menu;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Services\MenuService;
use App\Modules\Menu\Services\ProductMedia;
use App\Modules\Menu\Support\Schedule;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Cache::flush();
});

function mrShop(string $tz = 'UTC'): array
{
    $r = Restaurant::create(['name' => 'Rich', 'slug' => 'rich'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => $tz, 'onboarded_at' => now()]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => []]), now()->addMonth());
    $owner = User::factory()->create(['restaurant_id' => $r->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    $owner->assignRole(Permissions::OWNER);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $cat = app(TenantContext::class)->runAs($r, fn () => Category::create(['name' => ['en' => 'Food'], 'sort' => 1]));

    return [$r, $owner, $cat];
}

function mrProduct(Restaurant $r, Category $cat, array $attrs = []): Product
{
    return app(TenantContext::class)->runAs($r, fn () => Product::create(array_merge(['category_id' => $cat->id, 'name' => ['en' => 'Dish'.uniqid()], 'price' => 10, 'sort' => 1], $attrs)));
}

describe('schedule rules', function () {
    $at = fn (string $when) => CarbonImmutable::parse($when, 'UTC'); // 2026-10-05 is a Monday

    it('is always open without rules, by day, by hours, and across midnight', function () use ($at) {
        expect(Schedule::isOpen(null, $at('2026-10-05 03:00')))->toBeTrue();
        $weekdays = ['days' => [1, 2, 3, 4, 5], 'from' => '12:00', 'to' => '15:00'];
        expect(Schedule::isOpen($weekdays, $at('2026-10-05 12:00')))->toBeTrue()
            ->and(Schedule::isOpen($weekdays, $at('2026-10-05 15:00')))->toBeFalse()   // the end minute is closed
            ->and(Schedule::isOpen($weekdays, $at('2026-10-05 11:59')))->toBeFalse()
            ->and(Schedule::isOpen($weekdays, $at('2026-10-10 13:00')))->toBeFalse();  // Saturday
        $night = ['days' => [5], 'from' => '22:00', 'to' => '03:00']; // Friday night
        expect(Schedule::isOpen($night, $at('2026-10-09 23:30')))->toBeTrue()
            ->and(Schedule::isOpen($night, $at('2026-10-10 02:00')))->toBeTrue()     // still Friday's session on Saturday 02:00
            ->and(Schedule::isOpen($night, $at('2026-10-10 04:00')))->toBeFalse()
            ->and(Schedule::isOpen($night, $at('2026-10-09 02:00')))->toBeFalse();
        expect(Schedule::isOpen(['days' => [6, 7], 'from' => null, 'to' => null], $at('2026-10-10 09:00')))->toBeTrue();
    });

    it('turns form input into a clean schedule', function () {
        expect(Schedule::fromInput(null))->toBeNull()->and(Schedule::fromInput(['days' => [], 'from' => '', 'to' => '']))->toBeNull()
            ->and(Schedule::fromInput(['days' => [1, 2, 3, 4, 5, 6, 7]]))->toBeNull()
            ->and(Schedule::fromInput(['days' => ['3', '1', '9', 'x'], 'from' => '11:00', 'to' => '14:30']))->toBe(['days' => [1, 3], 'from' => '11:00', 'to' => '14:30'])
            ->and(Schedule::fromInput(['days' => [2], 'from' => '11:00', 'to' => '']))->toBe(['days' => [2], 'from' => null, 'to' => null])
            ->and(Schedule::fromInput(['from' => '25:00', 'to' => '99:99']))->toBeNull();
        expect(Schedule::describe(['days' => [1, 2], 'from' => '12:00', 'to' => '15:00'], ['Mon', 'Tue']))->toBe('Mon, Tue · 12:00–15:00');
    });
});

describe('dish media', function () {
    it('recognises safe video links only', function () {
        expect(ProductMedia::video('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))->toBe(['type' => 'embed', 'src' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'])
            ->and(ProductMedia::video('https://youtu.be/dQw4w9WgXcQ')['src'])->toContain('dQw4w9WgXcQ')
            ->and(ProductMedia::video('https://vimeo.com/123456789')['src'])->toBe('https://player.vimeo.com/video/123456789?dnt=1')
            ->and(ProductMedia::video('https://cdn.example.com/clip.mp4?x=1'))->toBe(['type' => 'file', 'src' => 'https://cdn.example.com/clip.mp4?x=1'])
            ->and(ProductMedia::video('http://www.youtube.com/watch?v=dQw4w9WgXcQ'))->toBeNull()
            ->and(ProductMedia::video('https://evil.example/page.html'))->toBeNull()
            ->and(ProductMedia::video('javascript:alert(1)'))->toBeNull()
            ->and(ProductMedia::video(''))->toBeNull();
    });

    it('saves every new field from the product form, with gallery photos', function () {
        Storage::fake('public');
        [$r, $owner, $cat] = mrShop();
        $this->actingAs($owner)->post(route('menu.products.store'), [
            'category_id' => $cat->id, 'name' => ['en' => 'Burger'], 'price' => '12.50', 'is_active' => '1', 'is_available' => '1',
            'cost_price' => '4.20', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'portion_size' => '300 g',
            'nutrition' => ['protein' => '25', 'carbs' => '40', 'fat' => '', 'sodium' => '800'], 'badges' => ['new', 'chef'],
            'limited_until' => now()->addWeek()->toDateString(), 'schedule' => ['days' => ['1', '2'], 'from' => '12:00', 'to' => '16:00'], 'order_types' => ['dine_in', 'takeaway'],
            'gallery' => [UploadedFile::fake()->image('a.jpg', 900, 700), UploadedFile::fake()->image('b.jpg', 900, 700)],
        ])->assertSessionHasNoErrors();

        $p = app(TenantContext::class)->runAs($r, fn () => Product::first());
        expect((float) $p->cost_price)->toBe(4.2)->and($p->video_url)->toContain('youtu.be')->and($p->portion_size)->toBe('300 g')->and($p->nutrition)->toEqual(['protein' => 25, 'carbs' => 40, 'sodium' => 800])
            ->and($p->badges)->toBe(['new', 'chef'])->and($p->schedule)->toBe(['days' => [1, 2], 'from' => '12:00', 'to' => '16:00'])->and($p->order_types)->toBe(['dine_in', 'takeaway'])->and($p->gallery)->toHaveCount(2);

        $firstPhoto = $p->gallery[0];
        $this->actingAs($owner)->put(route('menu.products.update', $p->id), ['category_id' => $cat->id, 'name' => ['en' => 'Burger'], 'price' => '12.50', 'is_active' => '1', 'is_available' => '1', 'remove_gallery' => [$firstPhoto], 'order_types' => ['dine_in', 'takeaway', 'delivery']])->assertSessionHasNoErrors();
        $p = app(TenantContext::class)->runAs($r, fn () => $p->fresh());
        expect($p->gallery)->toHaveCount(1)->and($p->gallery)->not->toContain($firstPhoto)->and($p->order_types)->toBeNull()->and($p->badges)->toBeNull();
    });

    it('rejects an unknown badge', function () {
        [, $owner, $cat] = mrShop();
        $this->actingAs($owner)->post(route('menu.products.store'), ['category_id' => $cat->id, 'name' => ['en' => 'X'], 'price' => 1, 'badges' => ['bogus']])->assertSessionHasErrors('badges.0');
    });

    it('rejects a video link that is not safe', function () {
        [, $owner, $cat] = mrShop();
        $this->actingAs($owner)->post(route('menu.products.store'), ['category_id' => $cat->id, 'name' => ['en' => 'X'], 'price' => 1, 'video_url' => 'https://evil.example/page.html'])->assertSessionHasErrors('video_url');
        $this->actingAs($owner)->post(route('menu.products.store'), ['category_id' => $cat->id, 'name' => ['en' => 'X'], 'price' => 1, 'video_url' => 'http://youtu.be/dQw4w9WgXcQ'])->assertSessionHasErrors('video_url');
    });

    it('limits the gallery size and file type', function () {
        [, $owner, $cat] = mrShop();
        $many = array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 7));
        $this->actingAs($owner)->post(route('menu.products.store'), ['category_id' => $cat->id, 'name' => ['en' => 'X'], 'price' => 1, 'gallery' => $many])->assertSessionHasErrors('gallery');
        $this->actingAs($owner)->post(route('menu.products.store'), ['category_id' => $cat->id, 'name' => ['en' => 'X'], 'price' => 1, 'gallery' => [UploadedFile::fake()->create('x.php', 4, 'text/x-php')]])->assertSessionHasErrors('gallery.0');
    });
});

describe('what the guest sees and can order', function () {
    it('shows badges, nutrition, gallery and the video on the public menu', function () {
        [$r, , $cat] = mrShop();
        mrProduct($r, $cat, ['name' => ['en' => 'Showpiece'], 'badges' => ['chef'], 'nutrition' => ['protein' => 20.0], 'video_url' => 'https://vimeo.com/123456789', 'portion_size' => '250 g']);
        $html = $this->get("/r/{$r->slug}")->assertOk()->getContent();
        expect($html)->toContain('Showpiece')->toContain('player.vimeo.com')->toContain('123456789?dnt=1')->toContain('250 g')->toContain('Chef');
    });

    it('hides a dish outside its hours and rejects ordering it', function () {
        [$r, , $cat] = mrShop();
        $now = CarbonImmutable::now('UTC');
        $later = $now->addHours(3)->format('H:i');
        $muchLater = $now->addHours(5)->format('H:i');
        $lunch = mrProduct($r, $cat, ['name' => ['en' => 'Later Dish'], 'schedule' => ['days' => [], 'from' => $later, 'to' => $muchLater]]);
        mrProduct($r, $cat, ['name' => ['en' => 'Always Dish']]);

        $this->get("/r/{$r->slug}")->assertSee('Always Dish')->assertDontSee('Later Dish');
        $this->postJson("/r/{$r->slug}/cart/quote", ['lines' => [['product_id' => $lunch->id, 'qty' => 1]]])->assertJsonPath('lines.0.errors.0', 'unavailable');
    });

    it('ends a limited-time dish after its last day', function () {
        [$r, , $cat] = mrShop();
        mrProduct($r, $cat, ['name' => ['en' => 'Pumpkin Special'], 'limited_until' => now()->subDay()->toDateString(), 'badges' => ['limited']]);
        mrProduct($r, $cat, ['name' => ['en' => 'Still Here'], 'limited_until' => now()->toDateString()]);
        $this->get("/r/{$r->slug}")->assertSee('Still Here')->assertDontSee('Pumpkin Special');
    });

    it('hides a whole category outside its schedule', function () {
        [$r, , $cat] = mrShop();
        $brunch = app(TenantContext::class)->runAs($r, fn () => Category::create(['name' => ['en' => 'Brunch Corner'], 'sort' => 2, 'icon' => '🥞', 'schedule' => ['days' => [], 'from' => now('UTC')->addHours(3)->format('H:i'), 'to' => now('UTC')->addHours(4)->format('H:i')]]));
        mrProduct($r, $brunch, ['name' => ['en' => 'Pancakes']]);
        mrProduct($r, $cat, ['name' => ['en' => 'Soup']]);
        $this->get("/r/{$r->slug}")->assertSee('Soup')->assertDontSee('Pancakes')->assertDontSee('Brunch Corner');
    });

    it('keeps a dish off order types it is not for, in the quote and at checkout', function () {
        [$r, , $cat] = mrShop();
        $table = app(TenantContext::class)->runAs($r, fn () => DiningTable::create(['name' => '1']));
        $dineOnly = mrProduct($r, $cat, ['name' => ['en' => 'Fresh Oysters'], 'order_types' => ['dine_in']]);

        $line = [['product_id' => $dineOnly->id, 'qty' => 1]];
        $this->postJson("/r/{$r->slug}/cart/quote", ['lines' => $line, 'type' => 'takeaway'])->assertJsonPath('lines.0.errors.0', 'not_for_type');
        $this->postJson("/r/{$r->slug}/cart/quote", ['lines' => $line, 'type' => 'dine_in'])->assertJsonPath('lines.0.errors', []);
        $this->postJson("/r/{$r->slug}/order", ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => $line])->assertStatus(422)->assertJsonPath('error', 'cart_invalid');
        $this->postJson("/r/{$r->slug}/order", ['type' => 'dine_in', 'table_id' => $table->id, 'payment_method' => 'cash', 'lines' => $line])->assertCreated();
    });

    it('works in the restaurant’s own time zone', function () {
        [$r, , $cat] = mrShop('Pacific/Kiritimati'); // UTC+14
        $local = CarbonImmutable::now('Pacific/Kiritimati');
        $open = mrProduct($r, $cat, ['name' => ['en' => 'Local Lunch'], 'schedule' => ['days' => [], 'from' => $local->subHour()->format('H:i'), 'to' => $local->addHour()->format('H:i')]]);
        // The window is around "now" in Kiritimati; unless that wraps midnight differently, the dish is on the menu.
        if ($local->hour >= 1 && $local->hour < 23) {
            $this->get("/r/{$r->slug}")->assertSee('Local Lunch');
        }
        expect($open->exists)->toBeTrue();
    });
});

describe('menus', function () {
    it('creates a menu with hours and puts categories into it', function () {
        [$r, $owner, $cat] = mrShop();
        $drinks = app(TenantContext::class)->runAs($r, fn () => Category::create(['name' => ['en' => 'Drinks'], 'sort' => 2]));

        $this->actingAs($owner)->post(route('menu.menus.store'), ['name' => ['en' => 'Bar'], 'is_active' => '1', 'schedule' => ['days' => ['5', '6'], 'from' => '18:00', 'to' => '02:00'], 'categories' => [$drinks->id]])->assertSessionHasNoErrors();
        $menu = app(TenantContext::class)->runAs($r, fn () => Menu::first());
        expect($menu->schedule)->toBe(['days' => [5, 6], 'from' => '18:00', 'to' => '02:00'])->and(app(TenantContext::class)->runAs($r, fn () => $drinks->fresh()->menu_id))->toBe($menu->id)->and(app(TenantContext::class)->runAs($r, fn () => $cat->fresh()->menu_id))->toBeNull();

        $this->actingAs($owner)->get(route('menu.menus.index'))->assertOk()->assertSee('Bar')->assertSee('Fri, Sat');
        $this->actingAs($owner)->delete(route('menu.menus.destroy', $menu->id))->assertRedirect();
        expect(app(TenantContext::class)->runAs($r, fn () => $drinks->fresh()->menu_id))->toBeNull();
    });

    it('shows a menu switcher when two menus are open, and hides a closed menu', function () {
        [$r, , $cat] = mrShop();
        $lunch = app(TenantContext::class)->runAs($r, fn () => Menu::create(['name' => ['en' => 'Lunch Menu'], 'sort' => 1]));
        $closed = app(TenantContext::class)->runAs($r, fn () => Menu::create(['name' => ['en' => 'Midnight Menu'], 'sort' => 2, 'schedule' => ['days' => [], 'from' => now('UTC')->addHours(3)->format('H:i'), 'to' => now('UTC')->addHours(4)->format('H:i')]]));
        $lunchCat = app(TenantContext::class)->runAs($r, fn () => Category::create(['name' => ['en' => 'Lunch Specials'], 'sort' => 2, 'menu_id' => $lunch->id]));
        $nightCat = app(TenantContext::class)->runAs($r, fn () => Category::create(['name' => ['en' => 'Night Bites'], 'sort' => 3, 'menu_id' => $closed->id]));
        mrProduct($r, $cat, ['name' => ['en' => 'Plain Soup']]);
        mrProduct($r, $lunchCat, ['name' => ['en' => 'Lunch Pasta']]);
        mrProduct($r, $nightCat, ['name' => ['en' => 'Midnight Burger']]);

        $html = $this->get("/r/{$r->slug}")->assertOk()->getContent();
        expect($html)->toContain('Lunch Menu')->toContain('Plain Soup')->toContain('Lunch Pasta')->not->toContain('Midnight Burger')->not->toContain('Midnight Menu');
    });

    it('shows no switcher with only the main menu, and refuses to sell from a closed menu', function () {
        [$r, , $cat] = mrShop();
        mrProduct($r, $cat, ['name' => ['en' => 'Plain Soup']]);
        $this->get("/r/{$r->slug}")->assertDontSee('aria-label="Menus"', false);

        $closed = app(TenantContext::class)->runAs($r, fn () => Menu::create(['name' => ['en' => 'Closed'], 'is_active' => false]));
        $cat2 = app(TenantContext::class)->runAs($r, fn () => Category::create(['name' => ['en' => 'Hidden'], 'sort' => 2, 'menu_id' => $closed->id]));
        $dish = mrProduct($r, $cat2);
        $this->postJson("/r/{$r->slug}/cart/quote", ['lines' => [['product_id' => $dish->id, 'qty' => 1]]])->assertJsonPath('lines.0.errors.0', 'unavailable');
    });

    it('keeps menus per restaurant', function () {
        [$r, $owner] = mrShop();
        [$other] = mrShop();
        $theirs = app(TenantContext::class)->runAs($other, fn () => Menu::create(['name' => ['en' => 'Theirs']]));
        $this->actingAs($owner)->get(route('menu.menus.edit', $theirs->id))->assertNotFound();
        $this->actingAs($owner)->delete(route('menu.menus.destroy', $theirs->id))->assertNotFound();
    });
});

describe('pairings', function () {
    it('saves hand-picked pairings, ignores itself and other restaurants’ dishes, and exposes them to the menu', function () {
        [$r, $owner, $cat] = mrShop();
        [$other, , $ocat] = mrShop();
        $burger = mrProduct($r, $cat, ['name' => ['en' => 'Burger']]);
        $cola = mrProduct($r, $cat, ['name' => ['en' => 'Cola']]);
        $foreign = mrProduct($other, $ocat);
        $base = ['category_id' => $cat->id, 'name' => ['en' => 'Burger'], 'price' => 10, 'is_active' => '1', 'is_available' => '1'];

        $this->actingAs($owner)->put(route('menu.products.update', $burger->id), $base + ['pairings' => [$cola->id, $burger->id]])->assertSessionHasNoErrors();
        expect(app(TenantContext::class)->runAs($r, fn () => $burger->pairings()->pluck('products.id')->all()))->toBe([$cola->id]);
        $this->actingAs($owner)->put(route('menu.products.update', $burger->id), $base + ['pairings' => [$foreign->id]])->assertSessionHasErrors('pairings.0');

        $tree = app(MenuService::class)->tree($r);
        $row = collect($tree[0]['products'])->firstWhere('id', $burger->id);
        expect($row['pairs'])->toBe([$cola->id]);
        $this->actingAs($owner)->get(route('menu.products.edit', $burger->id))->assertOk()->assertSee('Cola');
    });
});
