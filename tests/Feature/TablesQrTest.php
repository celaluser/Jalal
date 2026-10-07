<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Billing\Support\UsageRegistry;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Services\ThemeRegistry;
use App\Modules\Tables\Models\Area;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tables\Qr\QrCode;
use App\Modules\Tables\Qr\QrStyle;
use App\Modules\Tables\Services\TableQr;
use App\Modules\Tables\Services\TableService;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    UsageRegistry::register('tables', fn () => DiningTable::count());
});

function tqShop(array $limits = [], array $features = []): array
{
    $restaurant = Restaurant::create(['name' => 'Cafe', 'slug' => 'cafe'.uniqid(), 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => $limits, 'features' => $features]);
    app(SubscriptionService::class)->assign($restaurant, $plan, now()->addMonth());

    return [$restaurant, tqUser($restaurant, Permissions::OWNER)];
}

function tqUser(Restaurant $restaurant, string $role): User
{
    $user = User::factory()->create(['restaurant_id' => $restaurant->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($restaurant->id);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $user;
}

function tqIn(Restaurant $restaurant, Closure $callback): mixed
{
    return app(TenantContext::class)->runAs($restaurant, $callback);
}

function tqTable(Restaurant $r, string $name = 'Table 1', array $extra = []): DiningTable
{
    return tqIn($r, fn () => DiningTable::create(array_merge(['name' => $name, 'sort' => 1], $extra)));
}

describe('qr rendering', function () {
    it('encodes text into a square matrix with the three position markers', function () {
        $m = QrCode::matrix('https://example.com/r/cafe/t/abc');
        $n = count($m);

        expect($n)->toBeGreaterThanOrEqual(21)->and($n % 4)->toBe(1)
            ->and($m[0][0])->toBeTrue()->and($m[0][$n - 1])->toBeTrue()->and($m[$n - 1][0])->toBeTrue()->and($m[$n - 1][$n - 1])->toBeFalse()
            ->and(QrCode::matrix('https://example.com/r/cafe/t/abc'))->toBe($m);
    });

    it('draws well-formed SVG in every shape, with the chosen colours', function () {
        foreach (QrStyle::SHAPES as $shape) {
            $svg = QrCode::svg('https://example.com/x', new QrStyle('#0e6350', '#fffbe6', $shape));

            expect(simplexml_load_string($svg))->not->toBeFalse()->and($svg)->toContain('#0e6350')->toContain('#fffbe6')->toContain('viewBox');
        }
    });

    it('draws PNGs of the requested size in every shape', function () {
        foreach (QrStyle::SHAPES as $shape) {
            $info = getimagesizefromstring(QrCode::png('https://example.com/x', new QrStyle(shape: $shape), 300));

            expect($info[0])->toBe(300)->and($info[1])->toBe(300)->and($info['mime'])->toBe('image/png');
        }
    });

    it('places a logo in the centre, using stronger error correction', function () {
        $logo = imagecreatetruecolor(64, 64);
        ob_start();
        imagepng($logo);
        $png = ob_get_clean();

        $plain = QrCode::svg('https://example.com/x');
        $branded = QrCode::svg('https://example.com/x', new QrStyle(logo: $png));

        expect($plain)->not->toContain('<image')->and($branded)->toContain('<image')->toContain('data:image/png;base64,')
            ->and(getimagesizefromstring(QrCode::png('https://example.com/x', new QrStyle(logo: $png), 200))[0])->toBe(200);
    });

    it('refuses colours that are not plain hex values', function () {
        expect(fn () => new QrStyle('red'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new QrStyle('#000000', '#fff"><script>'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new QrStyle(shape: 'star'))->toThrow(InvalidArgumentException::class);
    });

    it('only calls dark-on-light codes with enough contrast scannable', function () {
        expect(QrStyle::isScannable('#000000', '#ffffff'))->toBeTrue()
            ->and(QrStyle::isScannable('#0e6350', '#ffffff'))->toBeTrue()
            ->and(QrStyle::isScannable('#ffffff', '#000000'))->toBeFalse()   // inverted
            ->and(QrStyle::isScannable('#aaaaaa', '#ffffff'))->toBeFalse()   // too faint
            ->and(QrStyle::isScannable('#ffb020', '#ffffff'))->toBeFalse();  // brand yellow on white
    });
});

describe('addresses and tokens', function () {
    it('points table codes at the restaurant address with the table token', function () {
        [$r] = tqShop();
        $t = tqTable($r);

        expect(app(TableQr::class)->url($r, $t))->toBe(url('/r/'.$r->slug.'/t/'.$t->token))
            ->and(app(TableQr::class)->url($r))->toBe(url('/r/'.$r->slug));
    });

    it('prefers a verified custom domain, then a subdomain, and honours the platform switches', function () {
        [$r] = tqShop();
        $r->update(['custom_domain' => 'menu.cafe.test', 'domain_verified_at' => now(), 'subdomain' => 'cafe']);

        expect($r->publicUrl('t/x'))->toBe(url('/r/'.$r->slug.'/t/x')); // both features off

        config(['tenancy.subdomains_enabled' => true, 'tenancy.base_domain' => 'qrmenu.test']);
        expect($r->publicUrl())->toBe('http://cafe.qrmenu.test');

        config(['tenancy.custom_domains_enabled' => true]);
        expect($r->publicUrl('t/x'))->toBe('http://menu.cafe.test/t/x');

        $r->update(['domain_verified_at' => null]);
        expect($r->publicUrl())->toBe('http://cafe.qrmenu.test');
    });

    it('gives every table a unique unguessable token that cannot be mass assigned', function () {
        [$r, $owner] = tqShop();
        $a = tqTable($r, 'A');
        $b = tqTable($r, 'B');

        expect($a->token)->toMatch('/^[a-z0-9]{12}$/')->and($a->token)->not->toBe($b->token);

        $this->actingAs($owner)->put(route('tables.update', $a->id), ['name' => 'A', 'token' => 'hacked'])->assertRedirect();
        expect(tqIn($r, fn () => $a->fresh()->token))->toBe($a->token);
    });

    it('retires the old token on regenerate and finds only active tables of the current restaurant', function () {
        [$r, $owner] = tqShop();
        [$other] = tqShop();
        $t = tqTable($r);
        $old = $t->token;
        $service = app(TableService::class);

        expect(tqIn($r, fn () => $service->findByToken($old)?->id))->toBe($t->id);

        $this->actingAs($owner)->post(route('tables.regenerate', $t->id))->assertRedirect();
        $new = tqIn($r, fn () => $t->fresh()->token);

        expect($new)->not->toBe($old)->and(tqIn($r, fn () => $service->findByToken($old)))->toBeNull()
            ->and(tqIn($other, fn () => $service->findByToken($new)))->toBeNull();

        tqIn($r, fn () => $t->update(['is_active' => false]));
        expect(tqIn($r, fn () => $service->findByToken($new)))->toBeNull();
    });
});

describe('tables', function () {
    it('creates, validates, edits and deletes tables', function () {
        [$r, $owner] = tqShop();
        $area = tqIn($r, fn () => Area::create(['name' => 'Terrace']));

        $this->actingAs($owner)->post(route('tables.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('tables.store'), ['name' => 'T1', 'area_id' => $area->id, 'seats' => 4])->assertRedirect();
        $this->post(route('tables.store'), ['name' => 'T1'])->assertSessionHasErrors('name'); // duplicate name
        $this->post(route('tables.store'), ['name' => 'T2', 'seats' => 0])->assertSessionHasErrors('seats');

        $t = tqIn($r, fn () => DiningTable::first());
        expect($t->area_id)->toBe($area->id)->and($t->seats)->toBe(4)->and($t->is_active)->toBeTrue();

        $this->put(route('tables.update', $t->id), ['name' => 'Window', 'seats' => 2])->assertRedirect(route('tables.index'));
        expect(tqIn($r, fn () => $t->fresh()))->name->toBe('Window')->is_active->toBeFalse()->area_id->toBeNull();

        $this->delete(route('tables.destroy', $t->id))->assertRedirect();
        expect(tqIn($r, fn () => DiningTable::count()))->toBe(0);
    });

    it('allows the same table name in different restaurants', function () {
        [$a, $ownerA] = tqShop();
        [$b, $ownerB] = tqShop();

        $this->actingAs($ownerA)->post(route('tables.store'), ['name' => 'T1'])->assertRedirect();
        $this->actingAs($ownerB)->post(route('tables.store'), ['name' => 'T1'])->assertSessionDoesntHaveErrors();

        expect(DiningTable::allTenants()->where('name', 'T1')->count())->toBe(2);
    });

    it('hides other restaurants tables and areas, and rejects foreign areas', function () {
        [$mine, $owner] = tqShop();
        [$theirs] = tqShop();
        $foreign = tqTable($theirs);
        $foreignArea = tqIn($theirs, fn () => Area::create(['name' => 'Secret']));

        $this->actingAs($owner);
        $this->get(route('tables.edit', $foreign->id))->assertNotFound();
        $this->put(route('tables.update', $foreign->id), ['name' => 'x'])->assertNotFound();
        $this->delete(route('tables.destroy', $foreign->id))->assertNotFound();
        $this->post(route('tables.regenerate', $foreign->id))->assertNotFound();
        $this->get(route('tables.qr.download', [$foreign->id, 'png']))->assertNotFound();
        $this->put(route('tables.areas.update', $foreignArea->id), ['name' => 'x'])->assertNotFound();
        $this->delete(route('tables.areas.destroy', $foreignArea->id))->assertNotFound();
        $this->post(route('tables.store'), ['name' => 'Mine', 'area_id' => $foreignArea->id])->assertSessionHasErrors('area_id');
        $this->get(route('tables.index'))->assertOk()->assertDontSee($foreign->token);
    });

    it('bulk creates numbered tables and skips existing names', function () {
        [$r, $owner] = tqShop();
        tqTable($r, 'Table 2');

        $this->actingAs($owner)->post(route('tables.bulk'), ['prefix' => 'Table', 'from' => 1, 'to' => 4, 'seats' => 2])->assertRedirect()->assertSessionHas('status');

        expect(tqIn($r, fn () => DiningTable::orderBy('sort')->pluck('name')->sort()->values()->all()))->toBe(['Table 1', 'Table 2', 'Table 3', 'Table 4'])
            ->and(tqIn($r, fn () => DiningTable::where('name', 'Table 3')->value('seats')))->toBe(2);
    });

    it('validates the bulk range', function () {
        [$r, $owner] = tqShop();

        $this->actingAs($owner)->post(route('tables.bulk'), ['from' => 5, 'to' => 2])->assertSessionHasErrors('to');
        $this->post(route('tables.bulk'), ['from' => 1, 'to' => 500])->assertSessionHasErrors('to');
        expect(tqIn($r, fn () => DiningTable::count()))->toBe(0);
    });

    it('stops at the plan limit for single and bulk creation', function () {
        [$r, $owner] = tqShop(['tables' => 3]);
        tqTable($r, 'T1');

        $this->actingAs($owner)->post(route('tables.bulk'), ['prefix' => 'B', 'from' => 1, 'to' => 5])->assertSessionHasErrors('limit');
        expect(tqIn($r, fn () => DiningTable::count()))->toBe(1);

        $this->post(route('tables.bulk'), ['prefix' => 'B', 'from' => 1, 'to' => 2])->assertSessionDoesntHaveErrors();
        $this->post(route('tables.store'), ['name' => 'One more'])->assertSessionHasErrors('limit');
        expect(tqIn($r, fn () => DiningTable::count()))->toBe(3);
        expect(tqIn($r, fn () => app(TableService::class)->remaining($r)))->toBe(0);
    });

    it('manages areas and keeps tables when an area is deleted', function () {
        [$r, $owner] = tqShop();

        $this->actingAs($owner)->post(route('tables.areas.store'), ['name' => 'Bar'])->assertRedirect();
        $this->post(route('tables.areas.store'), ['name' => 'Bar'])->assertSessionHasErrors('name');
        $area = tqIn($r, fn () => Area::first());
        $t = tqTable($r, 'T1', ['area_id' => $area->id]);

        $this->put(route('tables.areas.update', $area->id), ['name' => 'Roof'])->assertRedirect();
        expect(tqIn($r, fn () => $area->fresh()->name))->toBe('Roof');

        $this->delete(route('tables.areas.destroy', $area->id))->assertRedirect();
        expect(tqIn($r, fn () => Area::count()))->toBe(0)->and(tqIn($r, fn () => $t->fresh()->area_id))->toBeNull();
    });

    it('lets waiters look but not change, and keeps the kitchen out', function () {
        [$r, $owner] = tqShop();
        $waiter = tqUser($r, Permissions::WAITER);
        $kitchen = tqUser($r, Permissions::KITCHEN);
        $manager = tqUser($r, Permissions::MANAGER);
        $t = tqTable($r);

        $this->actingAs($waiter)->get(route('tables.index'))->assertOk();
        $this->post(route('tables.store'), ['name' => 'x'])->assertForbidden();
        $this->delete(route('tables.destroy', $t->id))->assertForbidden();
        $this->put(route('tables.qr.update'), ['fg' => '#000000', 'bg' => '#ffffff', 'shape' => 'square'])->assertForbidden();

        $this->actingAs($kitchen)->get(route('tables.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('tables.index'))->assertOk()->assertSee(__('tables.add_table'));
    });

    it('reports usage for the plan page', function () {
        [$r] = tqShop(['tables' => 2]);
        tqTable($r);

        expect(tqIn($r, fn () => UsageRegistry::count('tables', $r)))->toBe(1);
    });
});

describe('qr design and downloads', function () {
    it('saves a style and uses it for the codes', function () {
        [$r, $owner] = tqShop();

        $this->actingAs($owner)->put(route('tables.qr.update'), ['fg' => '#0E6350', 'bg' => '#FFFFFF', 'shape' => 'rounded', 'caption' => ' Scan me '])->assertRedirect();

        $s = app(TableQr::class)->settings($r->fresh());
        expect($s)->toMatchArray(['fg' => '#0e6350', 'bg' => '#ffffff', 'shape' => 'rounded', 'caption' => 'Scan me', 'logo' => false])
            ->and(app(TableQr::class)->svg($r->fresh()))->toContain('#0e6350')->toContain('rx=".32"');
    });

    it('rejects unscannable or malformed styles', function () {
        [$r, $owner] = tqShop();
        $this->actingAs($owner);

        $this->put(route('tables.qr.update'), ['fg' => '#ffffff', 'bg' => '#000000', 'shape' => 'square'])->assertSessionHasErrors('fg');
        $this->put(route('tables.qr.update'), ['fg' => '#ffb020', 'bg' => '#ffffff', 'shape' => 'square'])->assertSessionHasErrors('fg');
        $this->put(route('tables.qr.update'), ['fg' => 'black', 'bg' => '#ffffff', 'shape' => 'square'])->assertSessionHasErrors('fg');
        $this->put(route('tables.qr.update'), ['fg' => '#000000', 'bg' => '#ffffff', 'shape' => 'star'])->assertSessionHasErrors('shape');
        expect($r->fresh()->branding['qr'] ?? null)->toBeNull();
    });

    it('uses the logo only when the restaurant has one', function () {
        [$r, $owner] = tqShop();
        $this->actingAs($owner)->put(route('tables.qr.update'), ['fg' => '#000000', 'bg' => '#ffffff', 'shape' => 'square', 'logo' => 1]);
        expect(app(TableQr::class)->settings($r->fresh())['logo'])->toBeFalse();

        $this->post(route('restaurant.settings.branding'), ['color' => '#112233', 'logo' => UploadedFile::fake()->image('l.png', 120, 120)]);
        $this->put(route('tables.qr.update'), ['fg' => '#000000', 'bg' => '#ffffff', 'shape' => 'square', 'logo' => 1]);

        expect(app(TableQr::class)->settings($r->fresh())['logo'])->toBeTrue()->and(app(TableQr::class)->svg($r->fresh()))->toContain('<image');
    });

    it('previews unsaved values as SVG and rejects bad ones as JSON', function () {
        [$r, $owner] = tqShop();

        $this->actingAs($owner)->get(route('tables.qr.preview', ['fg' => '#123456', 'bg' => '#ffffff', 'shape' => 'dots']))
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('#123456', false)->assertSee('<circle', false);

        $this->getJson(route('tables.qr.preview', ['fg' => '#eeeeee', 'bg' => '#ffffff', 'shape' => 'dots']))->assertStatus(422)->assertJsonValidationErrors('fg');
    });

    it('downloads a table code as PNG or SVG, and the plain menu code', function () {
        [$r, $owner] = tqShop();
        $t = tqTable($r, 'Table 7');

        $png = $this->actingAs($owner)->get(route('tables.qr.download', [$t->id, 'png']))->assertOk()->assertHeader('Content-Type', 'image/png');
        expect(getimagesizefromstring($png->getContent())[0])->toBe(1024)->and($png->headers->get('Content-Disposition'))->toContain('qr-table-7.png');

        $this->get(route('tables.qr.download', [$t->id, 'svg']))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->get(route('tables.qr.download', ['menu', 'png']))->assertOk();
        $this->get(route('tables.qr.download', ['nope', 'png']))->assertNotFound();
        $this->get('/tables/qr/download/'.$t->id.'/exe')->assertNotFound();
    });

    it('zips every table with safe unique file names', function () {
        [$r, $owner] = tqShop();
        tqTable($r, 'Table 1', ['sort' => 1]);
        tqTable($r, 'table-1', ['sort' => 2]);
        tqTable($r, '../../evil', ['sort' => 3]);

        $response = $this->actingAs($owner)->get(route('tables.qr.zip', 'png'))->assertOk();
        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->sort()->values()->all();

        expect($names)->toBe(['evil.png', 'table-1-2.png', 'table-1.png']);
        $zip->close();
    });

    it('prints a PDF sheet and 404s without tables', function () {
        [$r, $owner] = tqShop();

        $this->actingAs($owner)->get(route('tables.qr.pdf'))->assertNotFound();
        $this->get(route('tables.qr.zip', 'png'))->assertNotFound();

        foreach (range(1, 7) as $i) {
            tqTable($r, "Table {$i}", ['sort' => $i]);
        }

        $pdf = $this->get(route('tables.qr.pdf'))->assertOk();
        expect(str_starts_with($pdf->baseResponse->getContent(), '%PDF'))->toBeTrue();
    });

    it('prints only the requested tables of this restaurant', function () {
        [$r, $owner] = tqShop();
        [$other] = tqShop();
        $mine = tqTable($r);
        $theirs = tqTable($other);

        $this->actingAs($owner)->get(route('tables.qr.pdf', ['tables' => [$theirs->id]]))->assertNotFound();
        $this->get(route('tables.qr.pdf', ['tables' => [$mine->id]]))->assertOk();
    });

    it('renders the design page', function () {
        [$r, $owner] = tqShop();

        $this->actingAs($owner)->get(route('tables.qr'))->assertOk()->assertSee(__('tables.qr_title'))->assertSee('<svg', false);
    });
});

describe('appearance', function () {
    it('defaults to the classic theme with its font, layout and corners', function () {
        [$r] = tqShop();

        expect(app(ThemeRegistry::class)->settings($r))->toMatchArray(['theme' => 'classic', 'font' => 'serif', 'layout' => 'list', 'radius' => 'soft', 'show_images' => true]);
    });

    it('saves theme and overrides, and falls back for unknown stored values', function () {
        [$r, $owner] = tqShop();

        $this->actingAs($owner)->put(route('appearance.update'), ['theme' => 'midnight', 'font' => 'sans', 'layout' => 'grid', 'radius' => 'sharp'])->assertRedirect();

        expect(app(ThemeRegistry::class)->settings($r->fresh()))->toMatchArray(['theme' => 'midnight', 'font' => 'sans', 'layout' => 'grid', 'radius' => 'sharp', 'show_images' => false]);

        $r->update(['theme' => 'deleted-theme', 'branding' => ['menu' => ['font' => 'comic-sans']]]);
        expect(app(ThemeRegistry::class)->settings($r->fresh()))->toMatchArray(['theme' => 'classic', 'font' => 'serif']);
    });

    it('rejects unknown choices', function () {
        [$r, $owner] = tqShop();

        $this->actingAs($owner)->put(route('appearance.update'), ['theme' => 'x', 'font' => 'y', 'layout' => 'z', 'radius' => 'w'])
            ->assertSessionHasErrors(['theme', 'font', 'layout', 'radius']);
    });

    it('lets only plans with the feature remove the credit', function () {
        [$free, $ownerFree] = tqShop();
        [$paid, $ownerPaid] = tqShop([], ['remove_branding' => true]);
        $data = ['theme' => 'classic', 'font' => 'sans', 'layout' => 'list', 'radius' => 'soft', 'hide_credit' => 1];

        $this->actingAs($ownerFree)->put(route('appearance.update'), $data);
        $this->actingAs($ownerPaid)->put(route('appearance.update'), $data);

        expect(app(ThemeRegistry::class)->settings($free->fresh())['show_credit'])->toBeTrue()
            ->and(app(ThemeRegistry::class)->settings($paid->fresh())['show_credit'])->toBeFalse();
    });

    it('builds safe css tokens from the theme and the brand colour', function () {
        [$r] = tqShop();
        $r->update(['theme' => 'midnight', 'branding' => ['color' => '#ffb020']]);
        $themes = app(ThemeRegistry::class);

        $tokens = $themes->tokens($r->fresh());
        expect($tokens['--menu-bg'])->toBe('#0e1014')->and($tokens['--menu-accent'])->toBe('#ffb020')->and($tokens['--menu-accent-fg'])->toBe('#0f1115')
            ->and($themes->css($r->fresh()))->toStartWith(':root{--menu-bg:#0e1014;')->toEndWith('}');

        $r->update(['branding' => ['color' => '#112244']]);
        expect($themes->tokens($r->fresh())['--menu-accent-fg'])->toBe('#ffffff');
    });

    it('is reserved for staff who manage settings', function () {
        [$r, $owner] = tqShop();
        $manager = tqUser($r, Permissions::MANAGER);

        $this->actingAs($manager)->get(route('appearance.edit'))->assertForbidden();
        $this->actingAs($owner)->get(route('appearance.edit'))->assertOk()->assertSee(__('menu.appearance.title'));
    });
});
