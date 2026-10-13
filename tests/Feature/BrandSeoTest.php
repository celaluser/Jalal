<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Cms\Models\BlogPost;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Services\RuntimeSettings;
use App\Modules\Core\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    foreach (['en' => 'English', 'tr' => 'Türkçe'] as $code => $name) {
        Language::firstOrCreate(['code' => $code], ['name' => $name, 'native_name' => $name, 'is_active' => true, 'is_default' => $code === 'en']);
    }
    Cache::flush();
    $this->settings = app(SettingsService::class);
});

function brandAdmin(): User
{
    $admin = User::factory()->create(['password' => 'long-enough-1']);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $admin->assignRole(Permissions::SUPER_ADMIN);

    return $admin;
}

it('uses the product name and tagline in the page title and share tags, and lists Google and other verification codes', function () {
    $this->settings->set('site.name', 'Menu Cloud');
    $this->settings->set('site.tagline', 'QR menus for every table');
    $this->settings->set('seo.google_site_verification', 'abcDEF123456_-xyz');
    $this->settings->set('seo.bing_verification', 'BING1234567890');
    $this->settings->set('seo.twitter_handle', '@menucloud');
    app(RuntimeSettings::class)->apply();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('<title>Menu Cloud — QR menus for every table</title>')
        ->toContain('name="google-site-verification" content="abcDEF123456_-xyz"')->toContain('name="msvalidate.01" content="BING1234567890"')
        ->toContain('rel="canonical"')->toContain('property="og:site_name" content="Menu Cloud"')->toContain('name="twitter:site" content="@menucloud"')
        ->toContain('index,follow,max-image-preview:large')->toContain('hreflang="tr"')->toContain('"@type":"Organization"')->toContain('"@type":"SoftwareApplication"');
});

it('never lets search engines list sign-in, panel and installer pages, and can close the whole site', function () {
    $this->get('/login')->assertSee('content="noindex,nofollow"', false);
    $this->actingAs(brandAdmin())->get(route('admin.dashboard'))->assertSee('content="noindex,nofollow"', false);

    $this->settings->set('seo.indexing', '0');
    $this->get('/')->assertSee('content="noindex,nofollow"', false)->assertDontSee('rel="canonical"', false);
});

it('serves a robots.txt from the settings', function () {
    $res = $this->get('/robots.txt')->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('text/plain');
    expect($res->getContent())->toContain('Disallow: /admin')->toContain('Disallow: /dashboard')->toContain('Sitemap: '.url('/sitemap.xml'))->not->toContain("Disallow: /\n");

    $this->settings->set('seo.robots_extra', 'Disallow: /p/old-page');
    expect($this->get('/robots.txt')->getContent())->toContain('Disallow: /p/old-page');

    $this->settings->set('seo.indexing', '0');
    expect($this->get('/robots.txt')->getContent())->toBe("User-agent: *\nDisallow: /\n");
    expect(file_exists(public_path('robots.txt')))->toBeFalse(); // a static file would hide the route
});

it('puts the logo where the product name is, a white tile for the dark side and the mail header', function () {
    $this->settings->set('general.logo', '/storage/branding/logo.webp');
    $page = $this->get('/login')->assertOk()->getContent();
    expect($page)->toContain('/storage/branding/logo.webp')->toContain('bg-white p-1'); // only one logo: shown on a white tile on the dark panel

    $this->settings->set('general.logo_dark', '/storage/branding/logo-light.webp');
    expect($this->get('/login')->getContent())->toContain('/storage/branding/logo-light.webp');

    $mail = view('core::mail.layout', ['body' => 'Hi'])->render();
    expect($mail)->toContain('/storage/branding/logo.webp');
});

it('keeps the admin settings for brand, SEO and Google in the right places and accepts a pasted verification tag', function () {
    $admin = brandAdmin();
    $this->actingAs($admin)->get('/admin/settings/google')->assertOk()->assertSee('GTM')->assertSee(__('admin.settings.google.search_console'));

    $this->actingAs($admin)->put('/admin/settings/google', [
        'ga_id' => 'G-ABC123XYZ', 'gtm_id' => 'GTM-ABCD123',
        'google_site_verification' => '<meta name="google-site-verification" content="tokenTOKEN1234567" />',
        'head_code' => '<meta name="p:domain_verify" content="x">',
    ])->assertSessionHasNoErrors();

    expect($this->settings->get('seo.google_site_verification'))->toBe('tokenTOKEN1234567')->and($this->settings->get('seo.google_tag_manager_id'))->toBe('GTM-ABCD123');
    $this->actingAs($admin)->put('/admin/settings/google', ['gtm_id' => 'nope'])->assertSessionHasErrors('gtm_id');
    $this->actingAs($admin)->put('/admin/settings/google', ['google_site_verification' => 'x y z'])->assertSessionHasErrors('google_site_verification');

    $html = $this->get('/blog')->getContent();
    expect($html)->toContain('p:domain_verify')->toContain('tokenTOKEN1234567')->toContain('GTM-ABCD123'); // the tag manager loads after consent, from the banner script
});

it('checks the reCAPTCHA v3 score against the line the admin chose', function () {
    $this->settings->set('security.recaptcha_enabled', '1');
    $this->settings->set('security.recaptcha_version', 'v3');
    $this->settings->set('security.recaptcha_min_score', '0.7');
    $this->settings->set('security.recaptcha_site_key', 'site-key');
    $this->settings->set('security.recaptcha_secret', 'secret', encrypt: true);
    $payload = ['name' => 'A', 'email' => 'a@example.com', 'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1', 'g-recaptcha-response' => 'tok'];

    Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.3])]);
    $this->post('/register', $payload)->assertSessionHasErrors('g-recaptcha-response');

    Http::swap(new Factory);
    Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.9])]);
    $this->post('/register', $payload)->assertSessionDoesntHaveErrors('g-recaptcha-response');

    $page = $this->get('/register')->getContent();
    expect($page)->toContain('recaptcha/api.js?render=site-key')->toContain('name="g-recaptcha-response"');
});

it('describes a blog post to search engines as an article', function () {
    BlogPost::create(['title' => 'Hello', 'slug' => 'hello', 'locale' => 'en', 'body' => 'Body', 'excerpt' => 'Short', 'is_published' => true, 'published_at' => now()]);
    $html = $this->get('/blog/hello')->assertOk()->getContent();
    expect($html)->toContain('property="og:type" content="article"')->toContain('"@type":"Article"')->toContain('<title>Hello · ');
});
