<?php

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Services\ProrationService;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Models\Media;
use App\Modules\Core\Services\FileUploader;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
});

function feShop(): Restaurant
{
    return Restaurant::create(['name' => 'Fe', 'slug' => 'fe'.uniqid(), 'locale' => 'en', 'currency_code' => 'USD']);
}

function fePlan(string $interval, float $price): Plan
{
    return Plan::create(['name' => ucfirst($interval).$price, 'slug' => 'p'.uniqid(), 'interval' => $interval, 'price' => $price, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
}

describe('thumbnails', function () {
    it('keeps a small copy next to a big image and removes both together', function () {
        Storage::fake('public');
        $media = app(FileUploader::class)->store(UploadedFile::fake()->image('big.jpg', 1600, 1200));
        expect($media->has_thumb)->toBeTrue()->and($media->thumbUrl())->toContain('_thumb.webp')->and($media->url())->not->toContain('_thumb');
        Storage::disk('public')->assertExists($media->path);
        Storage::disk('public')->assertExists(Media::thumbPath($media->path));

        app(FileUploader::class)->delete($media);
        Storage::disk('public')->assertMissing(Media::thumbPath($media->path));
    });

    it('does not make a thumbnail for an image that is already small', function () {
        Storage::fake('public');
        $media = app(FileUploader::class)->store(UploadedFile::fake()->image('small.png', 300, 200));
        expect($media->has_thumb)->toBeFalse()->and($media->thumbUrl())->toBe($media->url());
    });
});

describe('proration', function () {
    function feSubscribe(Restaurant $r, Plan $plan, int $daysUsed, int $daysTotal = 30, bool $paid = true): void
    {
        app(SubscriptionService::class)->assign($r, $plan, now()->addDays($daysTotal - $daysUsed), ['starts_at' => now()->subDays($daysUsed)]);

        if ($paid) {
            Invoice::create(['restaurant_id' => $r->id, 'plan_id' => $plan->id, 'number' => 'INV-'.uniqid(), 'status' => 'paid', 'currency_code' => 'USD', 'subtotal' => $plan->price, 'total' => $plan->price, 'items' => [], 'issued_at' => now()->subDays($daysUsed), 'paid_at' => now()->subDays($daysUsed)]);
        }
    }

    it('credits the unused part of a paid period', function () {
        $r = feShop();
        feSubscribe($r, fePlan('monthly', 30), 10); // 20 of 30 days left, 30.00 paid → 20.00 credit
        $credit = app(ProrationService::class)->credit($r, fePlan('monthly', 50));
        expect($credit)->toBeBetween(1990, 2010);

        $invoice = app(InvoiceService::class)->create($r, fePlan('monthly', 50), null, null, $credit);
        expect((float) $invoice->total)->toBe(round(50 - $credit / 100, 2))->and($invoice->items)->toHaveCount(2)->and($invoice->items[1]['amount'])->toBe(-$credit / 100);
    });

    it('gives nothing for trials, unpaid or free plans, other currencies, the same plan, or when switched off', function () {
        $svc = app(ProrationService::class);
        $r = feShop();
        $paid = fePlan('monthly', 30);
        feSubscribe($r, $paid, 10, paid: false);
        expect($svc->credit($r, fePlan('monthly', 50)))->toBe(0); // never paid for

        $r2 = feShop();
        $p = fePlan('monthly', 30);
        feSubscribe($r2, $p, 10);
        expect($svc->credit($r2, $p))->toBe(0)->and($svc->credit($r2, fePlan('free', 0)))->toBe(0);
        $eur = fePlan('monthly', 50);
        $eur->update(['currency_code' => 'EUR']);
        expect($svc->credit($r2, $eur))->toBe(0);

        app(SettingsService::class)->set('billing.proration', '0');
        expect($svc->credit($r2, fePlan('monthly', 50)))->toBe(0);
    });

    it('never makes an invoice negative', function () {
        $r = feShop();
        feSubscribe($r, fePlan('yearly', 300), 1, 365);
        $credit = app(ProrationService::class)->credit($r, fePlan('monthly', 10));
        $invoice = app(InvoiceService::class)->create($r, fePlan('monthly', 10), null, null, $credit);
        expect((float) $invoice->total)->toBe(0.0)->and($invoice->status)->toBe('paid');
    });
});

describe('captcha and custom code', function () {
    it('verifies Cloudflare Turnstile on public forms when enabled', function () {
        $s = app(SettingsService::class);
        $s->set('security.turnstile_enabled', '1');
        $s->set('security.turnstile_secret', 'secret');
        $s->set('security.turnstile_site_key', 'site');

        $this->get('/login')->assertSee('cf-turnstile', false);
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'x', 'cf-turnstile-response' => 'tok'])->assertSessionHasErrors('g-recaptcha-response');
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'x', 'cf-turnstile-response' => 'tok'])->assertSessionDoesntHaveErrors('g-recaptcha-response');
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'x'])->assertSessionHasErrors('g-recaptcha-response'); // no token at all
    });

    it('adds the admin’s custom CSS and JS to pages, and cannot be broken out of its tag', function () {
        $s = app(SettingsService::class);
        $s->set('general.custom_css', 'body{outline:1px solid red}</style><script>alert(1)</script>');
        $s->set('general.custom_js', 'window.fe=1;</script><b>x</b>');
        $html = $this->get('/login')->assertOk()->getContent();

        expect($html)->toContain('body{outline:1px solid red}')->toContain('window.fe=1;')->not->toContain('</style><script>alert(1)')->not->toContain('</script><b>x</b>');
    });
});

describe('landing look', function () {
    it('renders each theme and ignores an unknown one', function () {
        $s = app(SettingsService::class);
        foreach (['aurora', 'midnight', 'clean'] as $theme) {
            $s->set('site.landing_theme', $theme);
            $this->get('/')->assertOk()->assertSee('data-landing-theme="'.$theme.'"', false);
        }
        $s->set('site.landing_theme', 'nonsense');
        $this->get('/')->assertOk()->assertSee('data-landing-theme="aurora"', false);
    });

    it('lets the admin choose the look', function () {
        $this->seed(Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = App\Models\User::factory()->create();
        app(Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
        $admin->assignRole(App\Modules\Auth\Support\Permissions::SUPER_ADMIN);

        $this->actingAs($admin)->get(route('admin.landing.edit'))->assertOk()->assertSee('Midnight');
        $this->actingAs($admin)->put(route('admin.landing.update'), ['locale' => 'en', 'theme' => 'midnight', 'hero' => ['title' => 'T', 'cta_label' => 'Go'], 'pricing' => ['title' => 'P']])->assertSessionHasNoErrors();
        expect(app(SettingsService::class)->get('site.landing_theme'))->toBe('midnight');
    });
});
