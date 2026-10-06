<?php

use App\Models\User;
use App\Modules\Admin\Settings\SettingsSchema;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Services\PaymentProcessor;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Mail\EmailTemplateRenderer;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\EmailTemplate;
use App\Modules\Core\Services\RuntimeSettings;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\LanguagesAndCurrenciesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, LanguagesAndCurrenciesSeeder::class]);
    $this->admin = User::factory()->create(['password' => 'long-enough-1']);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->admin->assignRole(Permissions::SUPER_ADMIN);
    $this->settings = app(SettingsService::class);
});

/** Valid values for every field of a section, so tests can override just what they exercise. */
function formFor(string $section, array $overrides = []): array
{
    $defaults = [
        'general' => ['site_name' => 'Menu Cloud', 'default_language' => 'en', 'default_currency' => 'USD', 'timezone' => 'Europe/Istanbul'],
        'realtime' => ['driver' => 'polling'],
        'storage' => ['disk' => 'public'],
    ][$section] ?? [];

    return array_merge($defaults, $overrides);
}

describe('settings screens', function () {
    it('renders every section from the schema', function (string $section) {
        $this->actingAs($this->admin)->get("/admin/settings/{$section}")->assertOk()->assertSee(__(SettingsSchema::section($section)['title']));
    })->with(['general', 'seo', 'security', 'auth', 'mail', 'domains', 'ai', 'realtime', 'storage']);

    it('404s unknown sections', function () {
        $this->actingAs($this->admin)->get('/admin/settings/nope')->assertNotFound();
    });

    it('saves values, shows them again, and treats empty text as unset', function () {
        $this->actingAs($this->admin)->put('/admin/settings/general', formFor('general', ['support_email' => 'help@menu.test']))->assertSessionHasNoErrors();
        $this->put('/admin/settings/general', formFor('general', ['support_email' => '']));

        expect($this->settings->get('site.name'))->toBe('Menu Cloud')->and($this->settings->get('general.support_email'))->toBeNull();
        $this->get('/admin/settings/general')->assertSee('value="Menu Cloud"', false);
    });

    it('validates input per field', function () {
        $this->actingAs($this->admin);

        $this->put('/admin/settings/general', formFor('general', ['timezone' => 'Mars/Olympus']))->assertSessionHasErrors('timezone');
        $this->put('/admin/settings/general', formFor('general', ['default_language' => 'xx']))->assertSessionHasErrors('default_language');
        $this->put('/admin/settings/general', formFor('general', ['default_currency' => 'ZZZ']))->assertSessionHasErrors('default_currency');
        $this->put('/admin/settings/general', formFor('general', ['site_name' => '']))->assertSessionHasErrors('site_name');
        $this->put('/admin/settings/seo', ['ga_id' => 'not-an-id'])->assertSessionHasErrors('ga_id');
        $this->put('/admin/settings/seo', ['ga_id' => 'G-ABC123XYZ'])->assertSessionHasNoErrors();
        $this->put('/admin/settings/mail', ['port' => '99999'])->assertSessionHasErrors('port');
        $this->put('/admin/settings/domains', ['base_domain' => 'not a domain'])->assertSessionHasErrors('base_domain');
        $this->put('/admin/settings/realtime', ['driver' => 'carrier-pigeon'])->assertSessionHasErrors('driver');
    });

    it('stores secrets encrypted, never prints them back, and keeps them when the field is blank', function () {
        $this->actingAs($this->admin)->put('/admin/settings/ai', ['provider' => 'openai', 'openai_key' => 'sk-super-secret'])->assertSessionHasNoErrors();
        $this->put('/admin/settings/ai', ['provider' => 'openai', 'openai_key' => '']);

        expect($this->settings->get('ai.openai_key'))->toBe('sk-super-secret')
            ->and(DB::table('settings')->where('key', 'ai.openai_key')->value('value'))->not->toContain('sk-super-secret');

        expect($this->get('/admin/settings/ai')->getContent())->not->toContain('sk-super-secret');
    });

    it('stores toggles as 1/0 and keeps credit prices', function () {
        $this->actingAs($this->admin)->put('/admin/settings/security', ['cookie_banner' => '1', 'recaptcha_enabled' => '1']);
        expect($this->settings->get('security.cookie_banner'))->toBe('1');

        $this->put('/admin/settings/security', []);
        expect($this->settings->get('security.cookie_banner'))->toBe('0');

        $this->put('/admin/settings/ai', ['cost_menu_import' => '25']);
        expect($this->settings->get('ai.cost.menu_import'))->toBe('25');
    });

    it('uploads a logo as webp and can remove it again', function () {
        Storage::fake('public');
        $this->actingAs($this->admin);

        $this->put('/admin/settings/general', formFor('general', ['logo' => UploadedFile::fake()->image('logo.png', 400, 120)]))->assertSessionHasNoErrors();
        expect($this->settings->get('general.logo'))->toEndWith('.webp');

        $this->put('/admin/settings/general', formFor('general', ['remove_logo' => '1']));
        expect($this->settings->get('general.logo'))->toBeNull();
    });

    it('rejects non-image logos, including svg', function () {
        $this->actingAs($this->admin)->put('/admin/settings/general', formFor('general', ['logo' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml')]))->assertSessionHasErrors('logo');
        $this->put('/admin/settings/general', formFor('general', ['favicon' => UploadedFile::fake()->create('x.php', 5, 'text/x-php')]))->assertSessionHasErrors('favicon');
    });

    it('is closed to restaurant staff', function () {
        $r = Restaurant::create(['name' => 'R', 'slug' => 'r']);
        $owner = User::factory()->create(['restaurant_id' => $r->id]);

        $this->actingAs($owner)->get('/admin/settings/mail')->assertForbidden();
        $this->put('/admin/settings/general', formFor('general'))->assertForbidden();
    });
});

describe('runtime settings', function () {
    it('applies SMTP, sender, timezone, language, domains and realtime to the config', function () {
        foreach ([
            'site.name' => 'Menu Cloud', 'general.timezone' => 'Asia/Tokyo', 'general.default_language' => 'tr',
            'mail.host' => 'smtp.menu.test', 'mail.port' => '2525', 'mail.username' => 'u', 'mail.password' => 'p', 'mail.from_address' => 'no-reply@menu.test', 'mail.encryption' => 'ssl',
            'domains.subdomains_enabled' => '1', 'domains.base_domain' => 'Menu.Test', 'domains.custom_domains_enabled' => '0',
            'realtime.driver' => 'pusher', 'realtime.key' => 'k', 'realtime.secret' => 's', 'realtime.app_id' => '1', 'realtime.cluster' => 'eu',
            'storage.s3_bucket' => 'media', 'storage.s3_region' => 'eu-1', 'storage.s3_key' => 'ak', 'storage.s3_secret' => 'as',
        ] as $key => $value) {
            $this->settings->set($key, $value);
        }

        app(RuntimeSettings::class)->apply();

        expect(config('app.name'))->toBe('Menu Cloud')
            ->and(config('app.timezone'))->toBe('Asia/Tokyo')
            ->and(config('app.locale'))->toBe('tr')
            ->and(config('mail.default'))->toBe('smtp')
            ->and(config('mail.mailers.smtp.host'))->toBe('smtp.menu.test')
            ->and(config('mail.mailers.smtp.port'))->toBe(2525)
            ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps')
            ->and(config('mail.from.address'))->toBe('no-reply@menu.test')
            ->and(config('tenancy.subdomains_enabled'))->toBeTrue()
            ->and(config('tenancy.custom_domains_enabled'))->toBeFalse()
            ->and(config('tenancy.base_domain'))->toBe('menu.test')
            ->and(config('broadcasting.default'))->toBe('pusher')
            ->and(config('broadcasting.connections.pusher.options.cluster'))->toBe('eu')
            ->and(config('filesystems.disks.s3.bucket'))->toBe('media');

        date_default_timezone_set('UTC');
    });

    it('leaves .env values alone for settings that were never set and polls by default', function () {
        config(['mail.mailers.smtp.host' => 'env.host', 'tenancy.subdomains_enabled' => true]);
        app(RuntimeSettings::class)->apply();

        expect(config('mail.mailers.smtp.host'))->toBe('env.host')->and(config('tenancy.subdomains_enabled'))->toBeTrue()
            ->and(config('broadcasting.default'))->toBe('null');
    });

    it('reports success or the SMTP error when sending a test mail', function () {
        $this->actingAs($this->admin)->post('/admin/settings/mail/test')->assertSessionHas('status'); // log mailer in tests

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        $this->post('/admin/settings/mail/test')->assertSessionHasErrors('mail');
    });
});

describe('maintenance mode', function () {
    it('shows a 503 page to visitors and logged-in restaurant staff but not to super admins or the login screens', function () {
        $this->settings->set('general.maintenance', '1');
        $this->settings->set('general.maintenance_message', 'Back at noon');
        $r = Restaurant::create(['name' => 'R', 'slug' => 'r']);
        $staff = User::factory()->create(['restaurant_id' => $r->id]);

        $this->get('/')->assertStatus(503)->assertSee('Back at noon');
        $this->actingAs($staff)->get('/dashboard')->assertStatus(503);
        auth()->logout();
        $this->get('/login')->assertOk();

        $this->actingAs($this->admin)->get('/admin')->assertOk();
    });

    it('is off by default and when switched off again', function () {
        $this->get('/login')->assertOk();

        $this->settings->set('general.maintenance', '0');
        $this->get('/login')->assertOk();
    });

    it('never blocks payment webhooks', function () {
        $this->settings->set('general.maintenance', '1');

        $this->postJson('/webhooks/payments/stripe')->assertNotFound(); // 404 from the handler (gateway disabled), not 503
    });
});

describe('reCAPTCHA, cookie banner and meta tags', function () {
    beforeEach(function () {
        $this->settings->set('security.recaptcha_enabled', '1');
        $this->settings->set('security.recaptcha_secret', 'rc-secret', encrypt: true);
        $this->settings->set('security.recaptcha_site_key', 'site-key');
        $this->user = User::factory()->create(['password' => 'correct-horse']);
    });

    it('blocks logins without a valid token and allows them with one', function () {
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::sequence()->push(['success' => false])->push(['success' => true])]);
        $creds = ['email' => $this->user->email, 'password' => 'correct-horse'];

        $this->post('/login', $creds)->assertSessionHasErrors('g-recaptcha-response');
        $this->post('/login', $creds + ['g-recaptcha-response' => 'bad'])->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();

        $this->post('/login', $creds + ['g-recaptcha-response' => 'good'])->assertRedirect(route('dashboard'));
    });

    it('fails closed when Google cannot be reached', function () {
        Http::fake(['www.google.com/*' => fn () => throw new ConnectionException('down')]);

        $this->post('/login', ['email' => $this->user->email, 'password' => 'correct-horse', 'g-recaptcha-response' => 'x'])->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();
    });

    it('shows the widget on login only when enabled', function () {
        $this->get('/login')->assertSee('g-recaptcha', false)->assertSee('site-key');

        $this->settings->set('security.recaptcha_enabled', '0');
        $this->get('/login')->assertDontSee('g-recaptcha', false);
    });

    it('does nothing when disabled', function () {
        $this->settings->set('security.recaptcha_enabled', '0');

        $this->post('/login', ['email' => $this->user->email, 'password' => 'correct-horse'])->assertRedirect(route('dashboard'));
    });

    it('renders the cookie banner, loads analytics only behind consent code, and exposes meta tags', function () {
        $this->settings->set('seo.google_analytics_id', 'G-TEST12345');
        $this->settings->set('seo.meta_description', 'Best QR menus');
        $this->settings->set('security.cookie_text', 'We bake cookies');

        $page = $this->get('/login')->assertSee('We bake cookies')->assertSee('Best QR menus')->assertSee('G-TEST12345');
        // The analytics script is created from JS after consent, never as a plain <script src> tag.
        expect($page->getContent())->not->toContain('<script async src="https://www.googletagmanager.com');
    });

    it('hides the banner when switched off and no analytics are configured', function () {
        $this->settings->set('security.cookie_banner', '0');

        $this->get('/login')->assertDontSee(__('cookie.accept'));
    });
});

describe('e-mail templates', function () {
    beforeEach(fn () => $this->renderer = app(EmailTemplateRenderer::class));

    it('renders the built-in text with escaped variables', function () {
        $out = $this->renderer->render('welcome', ['name' => '<script>alert(1)</script>', 'restaurant' => 'Bella', 'login_url' => 'https://x.test/login']);

        expect($out['subject'])->toContain(config('app.name'))
            ->and($out['html'])->toContain('&lt;script&gt;')->not->toContain('<script>')
            ->and($out['html'])->toContain('href="https://x.test/login"');
    });

    it('cannot inject markdown, html or extra headers through variables or subjects', function () {
        EmailTemplate::create(['key' => 'welcome', 'locale' => 'en', 'subject' => "Hi {{name}}\r\nBcc: evil@x.test", 'body' => 'Hello {{name}}']);

        $out = $this->renderer->render('welcome', ['name' => '[click](http://evil.test) <b>x</b>'], 'en');

        expect($out['subject'])->not->toContain("\n")->not->toContain("\r")
            ->and($out['html'])->not->toContain('href="http://evil.test"')->not->toContain('<b>');
    });

    it('strips raw html and unsafe links written into the body by an admin', function () {
        EmailTemplate::create(['key' => 'welcome', 'locale' => 'en', 'subject' => 's', 'body' => "<script>x()</script>\n\n[a](javascript:alert(1))"]);

        $html = $this->renderer->render('welcome', [], 'en')['html'];

        expect($html)->not->toContain('<script')->not->toContain('javascript:');
    });

    it('prefers the locale override, falls back to English, then to the built-in text', function () {
        EmailTemplate::create(['key' => 'welcome', 'locale' => 'en', 'subject' => 'EN subject', 'body' => 'EN body']);
        EmailTemplate::create(['key' => 'welcome', 'locale' => 'tr', 'subject' => 'TR konu', 'body' => 'TR govde']);

        expect($this->renderer->render('welcome', [], 'tr')['subject'])->toBe('TR konu')
            ->and($this->renderer->render('welcome', [], 'ar')['subject'])->toBe('EN subject');

        EmailTemplate::query()->delete();
        expect($this->renderer->render('welcome', [], 'tr')['subject'])->toContain('Welcome');
    });

    it('lets optional templates be switched off but never required ones', function () {
        EmailTemplate::create(['key' => 'welcome', 'locale' => 'en', 'subject' => 's', 'body' => 'b', 'is_active' => false]);
        EmailTemplate::create(['key' => 'verify_email', 'locale' => 'en', 'subject' => 's', 'body' => 'b', 'is_active' => false]);

        expect($this->renderer->render('welcome', [], 'en'))->toBeNull()
            ->and($this->renderer->render('verify_email', [], 'en'))->not->toBeNull();
    });

    it('fills links whose braces CommonMark percent-encodes', function () {
        EmailTemplate::create(['key' => 'password_reset', 'locale' => 'en', 'subject' => 's', 'body' => '[Reset]({{action_url}})']);

        $html = $this->renderer->render('password_reset', ['action_url' => 'https://x.test/reset?a=1&b=2'], 'en')['html'];

        expect($html)->toContain('href="https://x.test/reset?a=1&amp;b=2"');
    });

    it('is edited from the admin screen: save, preview without saving, test, reset', function () {
        Mail::fake();
        $this->actingAs($this->admin);

        $this->get('/admin/email-templates')->assertOk()->assertSee('Password reset');
        $this->get('/admin/email-templates/welcome?locale=tr')->assertOk()->assertSee('Welcome to');

        $this->put('/admin/email-templates/welcome', ['locale' => 'tr', 'subject' => 'Hos geldin {{name}}', 'body' => 'Merhaba {{name}}', 'is_active' => '1'])->assertSessionHasNoErrors();
        expect(EmailTemplate::where(['key' => 'welcome', 'locale' => 'tr'])->value('subject'))->toBe('Hos geldin {{name}}');

        $this->post('/admin/email-templates/welcome/preview', ['locale' => 'tr', 'subject' => 'Draft', 'body' => 'Unsaved **draft**'])
            ->assertOk()->assertSee('Unsaved')->assertSee('<strong>draft</strong>', false);
        expect(EmailTemplate::where('key', 'welcome')->count())->toBe(1);

        $this->post('/admin/email-templates/welcome/test', ['locale' => 'tr'])->assertSessionHas('status');
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'welcome' && $m->hasTo($this->admin->email));

        $this->delete('/admin/email-templates/welcome', ['locale' => 'tr']);
        expect(EmailTemplate::count())->toBe(0);

        $this->get('/admin/email-templates/nope')->assertNotFound();
    });

    it('validates template edits and keeps required templates always on', function () {
        $this->actingAs($this->admin);

        $this->put('/admin/email-templates/welcome', ['locale' => 'xx', 'subject' => 's', 'body' => 'b'])->assertSessionHasErrors('locale');
        $this->put('/admin/email-templates/welcome', ['locale' => 'en', 'subject' => '', 'body' => 'b'])->assertSessionHasErrors('subject');

        $this->put('/admin/email-templates/verify_email', ['locale' => 'en', 'subject' => 's', 'body' => 'b']); // is_active omitted
        expect(EmailTemplate::where('key', 'verify_email')->value('is_active'))->toBeTruthy();
    });
});

describe('transactional e-mails', function () {
    it('sends password reset through the template', function () {
        Mail::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'password_reset' && $m->vars['action_url'] !== '' && str_contains($m->vars['action_url'], '/reset-password/'));
    });

    it('sends a receipt with the invoice PDF when a payment settles and respects the off switch', function () {
        Mail::fake();
        $r = Restaurant::create(['name' => 'Payer', 'slug' => 'payer']);
        $r->update(['owner_id' => User::factory()->create(['restaurant_id' => $r->id])->id]);
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro', 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD']);

        $invoice = app(InvoiceService::class)->create($r, $plan);
        app(PaymentProcessor::class)->settle($invoice, 'manual');

        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'invoice_paid' && $m->hasTo($r->owner->email) && count($m->attachments()) === 1);

        Mail::fake();
        EmailTemplate::create(['key' => 'invoice_paid', 'locale' => 'en', 'subject' => 's', 'body' => 'b', 'is_active' => false]);
        $second = app(InvoiceService::class)->create($r, $plan);
        app(PaymentProcessor::class)->settle($second, 'manual');
        Mail::assertNothingSent();
    });

    it('reminds owners once before their plan ends', function () {
        Mail::fake();
        $subs = app(SubscriptionService::class);
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro', 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD']);

        $soon = Restaurant::create(['name' => 'Soon', 'slug' => 'soon']);
        $soon->update(['owner_id' => User::factory()->create(['restaurant_id' => $soon->id])->id]);
        $subs->assign($soon, $plan, now()->addDays(2));

        $later = Restaurant::create(['name' => 'Later', 'slug' => 'later']);
        $later->update(['owner_id' => User::factory()->create(['restaurant_id' => $later->id])->id]);
        $subs->assign($later, $plan, now()->addDays(20));

        $canceled = Restaurant::create(['name' => 'Gone', 'slug' => 'gone']);
        $canceled->update(['owner_id' => User::factory()->create(['restaurant_id' => $canceled->id])->id]);
        $subs->cancel($subs->assign($canceled, $plan, now()->addDays(1)));

        $this->artisan('billing:remind')->assertSuccessful();
        $this->artisan('billing:remind')->assertSuccessful(); // second run must not re-send

        Mail::assertSent(TemplatedMail::class, 1);
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'subscription_expiring' && $m->hasTo($soon->owner->email));
    });
});

it('has a settings screen for every schema section (guards the list used by the dataset above)', function () {
    expect(array_keys(SettingsSchema::sections()))->toEqual(['general', 'seo', 'security', 'auth', 'mail', 'domains', 'ai', 'realtime', 'storage']);
});
