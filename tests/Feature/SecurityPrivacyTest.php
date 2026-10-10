<?php

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Models\Review;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Storefront\Http\Controllers\AccountController;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Mail::fake();
});

function spShop(array $marketing = []): array
{
    $r = Restaurant::create(['name' => 'Sec Cafe', 'slug' => 'sp'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'onboarded_at' => now(), 'marketing_settings' => $marketing]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => []]), now()->addMonth());
    $p = app(TenantContext::class)->runAs($r, fn () => Product::create(['category_id' => Category::create(['name' => ['en' => 'Food'], 'sort' => 1])->id, 'name' => ['en' => 'Pizza'], 'price' => 10, 'sort' => 1]));

    return [$r, $p];
}

function spOrder(Restaurant $r, Product $p, string $email, $at = null): Order
{
    $o = app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'Gus', 'customer_phone' => '+1 555 111 2222', 'customer_email' => $email, 'lines' => [['product_id' => $p->id, 'qty' => 1]]]));

    if ($at) {
        $o->forceFill(['created_at' => $at, 'status' => 'completed'])->save();
    }

    return $o;
}

describe('security headers', function () {
    it('hardens every page and forbids framing the panel and sign-in, but not the guest menu', function () {
        [$r] = spShop();
        $login = $this->get('/login')->assertOk();
        expect($login->headers->get('X-Content-Type-Options'))->toBe('nosniff')->and($login->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
            ->and($login->headers->get('Content-Security-Policy'))->toBe("frame-ancestors 'self'")->and($login->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
            ->and($login->headers->get('Permissions-Policy'))->toContain('camera=()');

        $menu = $this->get("/r/{$r->slug}")->assertOk();
        expect($menu->headers->get('X-Frame-Options'))->toBeNull()->and($menu->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    });

    it('adds HSTS only over https', function () {
        expect($this->get('/login')->headers->get('Strict-Transport-Security'))->toBeNull();
        expect($this->get('https://localhost/login')->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000');
    });
});

describe('guest privacy', function () {
    it('lets a signed-in guest download everything held about them, and nobody else', function () {
        [$r, $p] = spShop();
        [$other, $op] = spShop();
        $o = spOrder($r, $p, 'gus@example.com');
        spOrder($other, $op, 'gus@example.com');
        $customer = app(TenantContext::class)->runAs($r, fn () => Customer::first());
        app(TenantContext::class)->runAs($r, fn () => Review::create(['order_id' => $o->id, 'customer_id' => $customer->id, 'rating' => 5, 'nps' => 9, 'comment' => 'Great']));

        $this->get("/r/{$r->slug}/account/export")->assertForbidden();
        $res = $this->withSession([AccountController::sessionKey($r->id) => $customer->id])->get("/r/{$r->slug}/account/export")->assertOk()->assertHeader('Content-Type', 'application/json');
        $data = json_decode($res->streamedContent(), true);
        expect($data['profile']['email'])->toBe('gus@example.com')->and($data['orders'])->toHaveCount(1)->and($data['orders'][0]['items'][0]['name'])->toBe('Pizza')->and($data['reviews'][0]['comment'])->toBe('Great');
    });

    it('shows a consent banner only when tracking ids are set, and loads nothing before consent', function () {
        [$plain] = spShop();
        $this->get("/r/{$plain->slug}")->assertDontSee('qrm.consent', false);

        [$r] = spShop(['pixel_meta' => '1234567890']);
        $html = $this->get("/r/{$r->slug}")->assertOk()->assertSee('qrm.consent', false)->assertSee('May we use advertising cookies')->getContent();
        // the loader is defined but only called after consent is remembered
        expect($html)->toContain('window.qrmLoadPixels = function')->and($html)->toContain("=== '1') { window.qrmLoadPixels(); }");
        $this->get("/r/{$r->slug}?kiosk=1")->assertDontSee('May we use advertising cookies');
    });
});

describe('data retention', function () {
    it('does nothing unless the restaurant set a period', function () {
        [$r, $p] = spShop();
        spOrder($r, $p, 'old@example.com', now()->subYears(3));
        $this->artisan('privacy:prune')->assertSuccessful();
        expect(app(TenantContext::class)->runAs($r, fn () => Order::first()->customer_email))->toBe('old@example.com');
    });

    it('erases quiet guests and personal details of old finished orders, keeping the sales', function () {
        [$r, $p] = spShop(['retention_months' => 12]);
        [$other, $op] = spShop();
        $old = spOrder($r, $p, 'old@example.com', now()->subMonths(18));
        app(TenantContext::class)->runAs($r, fn () => Customer::first()->forceFill(['last_order_at' => now()->subMonths(18)])->save());
        $fresh = spOrder($r, $p, 'new@example.com');
        $foreign = spOrder($other, $op, 'keep@example.com', now()->subYears(5));

        $this->artisan('privacy:prune')->assertSuccessful();

        app(TenantContext::class)->runAs($r, function () use ($old, $fresh) {
            expect(Order::find($old->id))->customer_email->toBeNull()->customer_name->toBeNull()->customer_phone->toBeNull()->total_cents->toBe(1000)
                ->and(Order::find($fresh->id)->customer_email)->toBe('new@example.com')
                ->and(Customer::pluck('email')->all())->toBe(['new@example.com']);
        });
        expect(app(TenantContext::class)->runAs($other, fn () => Order::find($foreign->id)->customer_email))->toBe('keep@example.com');
    });

    it('is configured from the loyalty page and validated', function () {
        [$r] = spShop();
        $owner = User::factory()->create(['restaurant_id' => $r->id]);
        $reg = app(PermissionRegistrar::class);
        $reg->setPermissionsTeamId($r->id);
        $owner->assignRole('restaurant_owner');
        $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));
        $base = ['loyalty_every' => 4, 'loyalty_reward_type' => 'fixed', 'loyalty_reward_value' => 5, 'loyalty_valid_days' => 30, 'campaign_daily_cap' => 100];
        $this->actingAs($owner)->put(route('marketing.settings.update'), $base + ['retention_months' => 24])->assertSessionHasNoErrors();
        expect($r->fresh()->marketing_settings['retention_months'])->toBe(24);
        $this->actingAs($owner)->put(route('marketing.settings.update'), $base + ['retention_months' => 999])->assertSessionHasErrors('retention_months');
    });
});
