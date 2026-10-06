<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Cms\Models\BlogPost;
use App\Modules\Cms\Models\LandingPage;
use App\Modules\Cms\Models\Page;
use App\Modules\Cms\Services\LandingContent;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\LanguagesAndCurrenciesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, LanguagesAndCurrenciesSeeder::class]);
    $this->admin = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->admin->assignRole(Permissions::SUPER_ADMIN);
});

function post(array $o = []): BlogPost
{
    return BlogPost::create(array_merge(['locale' => 'en', 'slug' => 'hello-world', 'title' => 'Hello World', 'body' => 'Body text', 'is_published' => true, 'published_at' => now()->subDay()], $o));
}

describe('landing page', function () {
    it('shows built-in copy with no setup at all', function () {
        $this->get('/')->assertOk()->assertSee('Your menu, one scan away')->assertSee('Do my guests need to install an app?');
    });

    it('shows stored copy, section by section, in the visitor language with default-language fallback', function () {
        LandingPage::create(['locale' => 'en', 'content' => ['hero' => ['title' => 'English headline', 'subtitle' => 'EN sub', 'cta_label' => 'Go', 'secondary_label' => ''], 'faq' => [['question' => 'EN question?', 'answer' => 'EN answer']]]]);
        LandingPage::create(['locale' => 'tr', 'content' => ['hero' => ['title' => 'Turkce baslik', 'subtitle' => 'TR alt', 'cta_label' => 'Basla', 'secondary_label' => '']]]);

        $this->get('/?lang=tr')->assertSee('Turkce baslik')->assertSee('EN question?'); // faq not translated: falls back to English
        $this->flushSession();
        $this->get('/')->assertSee('English headline')->assertDontSee('Turkce baslik');
    });

    it('builds pricing from active plans only and links to registration', function () {
        Plan::create(['name' => 'Starter', 'slug' => 'starter', 'interval' => 'monthly', 'price' => 19, 'currency_code' => 'USD', 'trial_days' => 14, 'limits' => ['products' => 50, 'tables' => null], 'features' => ['custom_domain' => true]]);
        Plan::create(['name' => 'Hidden', 'slug' => 'hidden', 'interval' => 'monthly', 'price' => 5, 'currency_code' => 'USD', 'is_active' => false]);

        $this->get('/')->assertSee('Starter')->assertSee('19.00')->assertSee('14-day free trial')->assertSee('Custom domain')->assertSee('Unlimited')
            ->assertSee('register?plan=starter', false)->assertDontSee('Hidden');
    });

    it('points the buttons to login when registration is closed', function () {
        app(SettingsService::class)->set('auth.registration_enabled', '0');
        Plan::create(['name' => 'Starter', 'slug' => 'starter', 'interval' => 'monthly', 'price' => 19, 'currency_code' => 'USD']);

        $this->get('/')->assertDontSee('register?plan=starter', false)->assertDontSee(route('register'));
    });

    it('treats admin-written text as text, not markup', function () {
        LandingPage::create(['locale' => 'en', 'content' => ['hero' => ['title' => '<script>alert(1)</script>', 'subtitle' => 'x', 'cta_label' => 'Go', 'secondary_label' => '']]]);

        $this->get('/')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    });

    it('is editable per language and drops empty or half-filled repeater rows', function () {
        $this->actingAs($this->admin)->put('/admin/landing', [
            'locale' => 'tr',
            'hero' => ['title' => 'Baslik', 'subtitle' => '', 'cta_label' => 'Basla', 'secondary_label' => ''],
            'pricing' => ['title' => 'Fiyat', 'subtitle' => ''],
            'features' => [['title' => 'Hizli', 'text' => 'Cok hizli'], ['title' => '', 'text' => ''], ['title' => 'Yarim', 'text' => '']],
            'faq' => [['question' => 'Soru?', 'answer' => 'Cevap']],
            'testimonials' => [['name' => 'Ayse', 'role' => '', 'quote' => 'Harika'], ['name' => 'Eksik', 'role' => '', 'quote' => '']],
            'contact' => ['title' => 'Bize ulasin', 'email' => 'hi@menu.test', 'text' => '', 'phone' => '', 'address' => ''],
        ])->assertSessionHasNoErrors();

        $stored = LandingPage::where('locale', 'tr')->first()->content;
        expect($stored['features'])->toBe([['title' => 'Hizli', 'text' => 'Cok hizli']])
            ->and($stored['testimonials'])->toHaveCount(1)
            ->and($stored['contact']['email'])->toBe('hi@menu.test');

        $this->get('/admin/landing?locale=tr')->assertOk()->assertSee('Baslik')->assertSee('Hizli');
        $this->get('/?lang=tr')->assertSee('hi@menu.test');
    });

    it('validates the landing editor', function () {
        $this->actingAs($this->admin);

        $this->put('/admin/landing', ['locale' => 'xx', 'hero' => ['title' => 't', 'cta_label' => 'c'], 'pricing' => ['title' => 'p']])->assertSessionHasErrors('locale');
        $this->put('/admin/landing', ['locale' => 'en', 'hero' => ['title' => '', 'cta_label' => ''], 'pricing' => ['title' => '']])->assertSessionHasErrors(['hero.title', 'hero.cta_label', 'pricing.title']);
        $this->put('/admin/landing', ['locale' => 'en', 'hero' => ['title' => 't', 'cta_label' => 'c'], 'pricing' => ['title' => 'p'], 'contact' => ['email' => 'nope']])->assertSessionHasErrors('contact.email');
        $this->put('/admin/landing', ['locale' => 'en', 'hero' => ['title' => 't', 'cta_label' => 'c'], 'pricing' => ['title' => 'p'], 'features' => array_fill(0, 13, ['title' => 'a', 'text' => 'b'])])->assertSessionHasErrors('features');
    });

    it('resolves content with the service the same way', function () {
        expect(app(LandingContent::class)->for('ar')['hero']['title'])->toBe('Your menu, one scan away');
    });
});

describe('blog', function () {
    it('lists only published, due posts and 404s the rest', function () {
        post();
        post(['slug' => 'draft', 'title' => 'A Draft', 'is_published' => false]);
        post(['slug' => 'future', 'title' => 'Future Post', 'published_at' => now()->addWeek()]);

        $this->get('/blog')->assertOk()->assertSee('Hello World')->assertDontSee('A Draft')->assertDontSee('Future Post');
        $this->get('/blog/hello-world')->assertOk()->assertSee('Body text');
        $this->get('/blog/draft')->assertNotFound();
        $this->get('/blog/future')->assertNotFound();
        $this->get('/blog/missing')->assertNotFound();
    });

    it('renders markdown but strips scripts, raw html and javascript links', function () {
        post(['slug' => 'md', 'body' => "## Heading\n\n**bold** text\n\n<script>alert(1)</script>\n\n<img src=x onerror=alert(2)>\n\n[click](javascript:alert(3))\n\n[safe](https://example.com)"]);

        $page = $this->get('/blog/md')->assertOk();
        $html = $page->getContent();

        expect($html)->toContain('<h2>Heading</h2>')->toContain('<strong>bold</strong>')->toContain('href="https://example.com"')
            ->not->toContain('alert(1)')->not->toContain('onerror')->not->toContain('javascript:');
    });

    it('shows posts in the visitor language and falls back to the default language', function () {
        post(['slug' => 'merhaba', 'locale' => 'tr', 'title' => 'Merhaba Dunya']);
        post(['slug' => 'english-only', 'title' => 'English Only']);

        $this->get('/blog?lang=tr')->assertSee('Merhaba Dunya')->assertDontSee('English Only');
        $this->get('/blog/english-only')->assertOk()->assertSee('English Only'); // not translated: default language shown

        $this->flushSession();
        $this->get('/blog')->assertSee('English Only')->assertDontSee('Merhaba Dunya');
    });

    it('serves a language with no posts of its own the default-language list', function () {
        post();

        $this->get('/blog?lang=ar')->assertSee('Hello World');
    });

    it('is managed from the admin: create with cover, schedule, edit, delete', function () {
        Storage::fake('public');
        $this->actingAs($this->admin);

        $this->post('/admin/posts', ['locale' => 'en', 'title' => 'Launch', 'slug' => 'launch', 'body' => 'We launched', 'is_published' => '1', 'cover' => UploadedFile::fake()->image('c.jpg', 800, 400)])->assertSessionHasNoErrors();
        $post = BlogPost::firstWhere('slug', 'launch');
        expect($post->cover_url)->toEndWith('.webp')->and($post->published_at)->not->toBeNull()->and($post->author_id)->toBe($this->admin->id);

        $this->get('/blog')->assertSee('Launch');

        $this->put("/admin/posts/{$post->id}", ['locale' => 'en', 'title' => 'Launch', 'slug' => 'launch', 'body' => 'x', 'is_published' => '1', 'published_at' => now()->addMonth()->format('Y-m-d\TH:i')]);
        $this->get('/blog')->assertDontSee('Launch');

        $this->delete("/admin/posts/{$post->id}");
        expect(BlogPost::count())->toBe(0);
    });

    it('allows the same slug in different languages but not twice in one', function () {
        $this->actingAs($this->admin);
        $base = ['title' => 'T', 'slug' => 'same', 'body' => 'b'];

        $this->post('/admin/posts', $base + ['locale' => 'en'])->assertSessionHasNoErrors();
        $this->post('/admin/posts', $base + ['locale' => 'tr'])->assertSessionHasNoErrors();
        $this->post('/admin/posts', $base + ['locale' => 'en'])->assertSessionHasErrors('slug');
        $this->post('/admin/posts', ['locale' => 'en', 'title' => 'T', 'slug' => 'Bad Slug', 'body' => 'b'])->assertSessionHasErrors('slug');
        $this->post('/admin/posts', ['locale' => 'en', 'title' => 'T', 'slug' => 'ok', 'body' => 'b', 'cover' => UploadedFile::fake()->create('x.php', 5, 'text/x-php')])->assertSessionHasErrors('cover');
    });
});

describe('static pages', function () {
    it('serves published pages and hides drafts', function () {
        Page::create(['locale' => 'en', 'slug' => 'privacy', 'title' => 'Privacy Policy', 'body' => 'We respect **you**', 'is_published' => true]);
        Page::create(['locale' => 'en', 'slug' => 'secret', 'title' => 'Secret', 'body' => 'x', 'is_published' => false]);

        $this->get('/p/privacy')->assertOk()->assertSee('Privacy Policy')->assertSee('<strong>you</strong>', false);
        $this->get('/p/secret')->assertNotFound();
    });

    it('links footer pages of the visitor language on every public page', function () {
        Page::create(['locale' => 'en', 'slug' => 'terms', 'title' => 'Terms of Service', 'body' => 'x', 'is_published' => true, 'in_footer' => true]);
        Page::create(['locale' => 'en', 'slug' => 'imprint', 'title' => 'Imprint', 'body' => 'x', 'is_published' => true, 'in_footer' => false]);

        $this->get('/')->assertSee('Terms of Service')->assertDontSee('Imprint');
        $this->get('/blog')->assertSee('Terms of Service');
    });

    it('is managed from the admin with validation', function () {
        $this->actingAs($this->admin);

        $this->post('/admin/pages', ['locale' => 'en', 'title' => 'About', 'slug' => 'about', 'body' => 'We cook', 'is_published' => '1', 'in_footer' => '1'])->assertSessionHasNoErrors();
        $this->get('/p/about')->assertOk();

        $this->post('/admin/pages', ['locale' => 'en', 'title' => 'About 2', 'slug' => 'about', 'body' => 'x'])->assertSessionHasErrors('slug');
        $this->post('/admin/pages', ['locale' => 'en', 'title' => '', 'slug' => 'x', 'body' => ''])->assertSessionHasErrors(['title', 'body']);

        $page = Page::firstWhere('slug', 'about');
        $this->flushSession(); // drop the old input of the failed submits above, as a redirect back to the form would
        $this->get("/admin/pages/{$page->id}/edit")->assertSee('value="About"', false);
        $this->delete("/admin/pages/{$page->id}");
        expect(Page::count())->toBe(0);
    });
});

describe('sitemap and access', function () {
    it('lists the landing page, blog, visible posts and published pages only', function () {
        post();
        post(['slug' => 'draft', 'is_published' => false]);
        post(['slug' => 'merhaba', 'locale' => 'tr']);
        Page::create(['locale' => 'en', 'slug' => 'terms', 'title' => 'T', 'body' => 'x', 'is_published' => true]);
        Page::create(['locale' => 'en', 'slug' => 'hidden', 'title' => 'H', 'body' => 'x', 'is_published' => false]);

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->getContent();

        expect($xml)->toContain('<loc>'.url('/').'</loc>')->toContain('/blog/hello-world')->toContain('/blog/merhaba?lang=tr')->toContain('/p/terms')
            ->not->toContain('draft')->not->toContain('/p/hidden');
        expect(fn () => simplexml_load_string($xml, options: LIBXML_NOERROR))->not->toThrow(Exception::class)
            ->and(simplexml_load_string($xml))->not->toBeFalse();
    });

    it('keeps the CMS admin closed to restaurant staff and guests', function (string $url) {
        $this->get($url)->assertRedirect(route('login'));
        $owner = User::factory()->create(['restaurant_id' => Restaurant::create(['name' => 'R', 'slug' => 'r'])->id]);

        $this->actingAs($owner)->get($url)->assertForbidden();
    })->with(['/admin/landing', '/admin/pages', '/admin/pages/create', '/admin/posts', '/admin/posts/create']);

    it('sends logged-in restaurant owners to their dashboard link in the header', function () {
        $owner = User::factory()->create(['restaurant_id' => Restaurant::create(['name' => 'R', 'slug' => 'r'])->id]);

        $this->actingAs($this->admin)->get('/')->assertSee(route('admin.dashboard'));
        $this->actingAs($owner)->get('/')->assertSee(route('dashboard'));
    });
});
