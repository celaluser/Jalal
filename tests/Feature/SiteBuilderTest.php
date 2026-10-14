<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Models\Media;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

function sbShop(): array
{
    $r = Restaurant::create(['name' => 'Builder Bistro', 'slug' => 'sb'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en', 'tr'], 'currency_code' => 'USD', 'onboarded_at' => now(), 'phone' => '+90 212 000 00 00', 'site_settings' => ['site_enabled' => true]]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => []]), now()->addMonth());
    $u = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $u->assignRole(Permissions::OWNER);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return [$r, $u];
}

function sbPicture(Restaurant $r, string $name = 'a.webp'): Media
{
    return Media::withoutGlobalScopes()->create(['restaurant_id' => $r->id, 'disk' => 'public', 'path' => 'site/r'.$r->id.'/'.$name, 'mime' => 'image/webp', 'size' => 1000]);
}

function sbSave($test, User $u, array $blocks, array $gallery = [], array $extra = [])
{
    return $test->actingAs($u)->put(route('site.update'), $extra + ['site_enabled' => 1, 'indexable' => 1, 'blocks_json' => json_encode($blocks), 'gallery_json' => json_encode($gallery)]);
}

it('saves sections and gallery, cleans them, and shows them on the About page in order', function () {
    [$r, $u] = sbShop();
    [, $stranger] = sbShop();
    $strangerRestaurant = $stranger->restaurant;
    $mine = sbPicture($r);
    $photo = sbPicture($r, 'b.webp');
    $theirs = sbPicture($strangerRestaurant, 'c.webp');

    $this->actingAs($u)->get(route('site.edit'))->assertOk()->assertSee(__('sitebuilder.sections_title'))->assertSee(__('sitebuilder.gallery_title'));

    sbSave($this, $u, [
        ['type' => 'text', 'title' => ['en' => 'Our story <script>x</script>', 'tr' => 'Hikayemiz', 'de' => 'ignored'], 'body' => ['en' => "Hello **world** <img src=x onerror=alert(1)>\n\n- one\n- two"]],
        ['type' => 'image', 'media' => $mine->id, 'caption' => ['en' => 'The terrace']],
        ['type' => 'image', 'media' => $theirs->id, 'caption' => ['en' => 'Not ours']],
        ['type' => 'button', 'label' => ['en' => 'Book a table'], 'target' => 'reserve', 'style' => 'dark'],
        ['type' => 'button', 'label' => ['en' => 'Evil'], 'target' => 'custom', 'url' => 'javascript:alert(1)'],
        ['type' => 'button', 'label' => ['en' => 'Press'], 'target' => 'custom', 'url' => 'https://press.example.com/story'],
        ['type' => 'gallery'],
        ['type' => 'hours'],
        ['type' => 'nonsense'],
    ], [['media' => $photo->id, 'caption' => 'Soup <b>of the day</b>'], ['media' => $theirs->id, 'caption' => 'stolen'], ['media' => $photo->id, 'caption' => 'dup']])->assertSessionHasNoErrors()->assertRedirect();

    $saved = $r->fresh()->site_settings;
    expect(collect($saved['blocks'])->pluck('type')->all())->toBe(['text', 'image', 'button', 'button', 'gallery', 'hours'])
        ->and($saved['blocks'][0]['title'])->toBe(['en' => 'Our story x', 'tr' => 'Hikayemiz'])
        ->and($saved['blocks'][3]['url'])->toBe('https://press.example.com/story')
        ->and($saved['gallery'])->toBe([['media' => $photo->id, 'caption' => 'Soup of the day']]);

    $html = $this->get('/r/'.$r->slug.'/about')->assertOk()->getContent();
    expect($html)->toContain('Our story x')->toContain('<strong>world</strong>')->toContain('<li>one</li>')->not->toContain('onerror')->not->toContain('<script>x')
        ->toContain('alt="The terrace"')->toContain('Book a table')->toContain('/reserve')->not->toContain('Evil')->not->toContain('Not ours')->toContain('https://press.example.com/story')
        ->toContain(__('sitebuilder.photos'))->toContain('alt="Soup of the day"')->not->toContain('stolen');
    expect(strpos($html, 'Our story x'))->toBeLessThan(strpos($html, 'The terrace'))->and(strpos($html, 'The terrace'))->toBeLessThan(strpos($html, 'Book a table'))
        ->and(strpos($html, 'Book a table'))->toBeLessThan(strpos($html, __('sitebuilder.photos')));

    // Turkish visitors get the Turkish text where there is one.
    expect($this->get('/r/'.$r->slug.'/about?lang=tr')->getContent())->toContain('Hikayemiz');
});

it('shows the standard layout with the gallery when there are no sections', function () {
    [$r, $u] = sbShop();
    $photo = sbPicture($r);
    sbSave($this, $u, [], [['media' => $photo->id, 'caption' => 'Pasta']], ['about' => ['en' => 'We cook.']])->assertRedirect();

    $html = $this->get('/r/'.$r->slug.'/about')->assertOk()->assertSee('We cook.')->assertSee(__('sitebuilder.photos'))->getContent();
    expect($html)->toContain('alt="Pasta"');

    // Photos are also offered to search engines in the structured data.
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    expect(json_encode(json_decode($m[1], true)['image']))->toContain('storage');
});

it('keeps the limits: 30 sections, 24 photos, tolerant of broken JSON', function () {
    [$r, $u] = sbShop();
    sbSave($this, $u, array_fill(0, 40, ['type' => 'hours']))->assertRedirect();
    expect(count($r->fresh()->site_settings['blocks']))->toBe(30);

    $this->actingAs($u)->put(route('site.update'), ['blocks_json' => '{not json', 'gallery_json' => '"x"'])->assertSessionHasNoErrors();
    expect($r->fresh()->site_settings['blocks'])->toBe([])->and($r->fresh()->site_settings['gallery'])->toBe([]);

    $pics = collect(range(1, 30))->map(fn ($i) => ['media' => sbPicture($r, "p{$i}.webp")->id, 'caption' => ''])->all();
    sbSave($this, $u, [], $pics)->assertRedirect();
    expect(count($r->fresh()->site_settings['gallery']))->toBe(24);
});

it('keeps the editor and uploads to staff with the permission', function () {
    [$r] = sbShop();
    $waiter = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $waiter->assignRole(Permissions::WAITER);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));

    $this->actingAs($waiter)->get(route('site.edit'))->assertForbidden();
    $this->actingAs($waiter)->post(route('site.media'))->assertForbidden();
});

it('uploads a picture for a section', function () {
    [$r, $u] = sbShop();
    $this->actingAs($u)->postJson(route('site.media'), [])->assertStatus(422);
    $this->actingAs($u)->postJson(route('site.media'), ['image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertStatus(422);
})->group('uploads');

it('stores an uploaded picture under the restaurant', function () {
    [$r, $u] = sbShop();
    $res = $this->actingAs($u)->postJson(route('site.media'), ['image' => UploadedFile::fake()->image('terrace.jpg', 800, 600)])->assertCreated();

    expect(Media::withoutGlobalScopes()->find($res->json('id'))->restaurant_id)->toBe($r->id)->and($res->json('url'))->toBeString();
})->skip(! extension_loaded('gd'), 'Needs the GD extension to build and re-encode a test picture.');
