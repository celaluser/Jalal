<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Payments\GatewayManager;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Services\PlanNotices;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Billing\Services\UsageReport;
use App\Modules\Billing\Support\UsageRegistry;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    UsageRegistry::flush();
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

afterEach(fn () => UsageRegistry::flush());

function p4Plan(array $overrides = []): Plan
{
    return Plan::create(array_merge([
        'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'interval' => 'monthly', 'price' => 20, 'currency_code' => 'USD',
        'trial_days' => 0, 'limits' => ['products' => 10, 'tables' => null], 'features' => [], 'is_active' => true,
    ], $overrides));
}

function p4Staff(string $role = Permissions::OWNER, ?Restaurant $restaurant = null, bool $onboarded = true): User
{
    $restaurant ??= Restaurant::create(['name' => 'Cafe', 'slug' => 'cafe'.uniqid(), 'onboarded_at' => $onboarded ? now() : null]);
    $user = User::factory()->create(['restaurant_id' => $restaurant->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($restaurant->id);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));

    if ($role === Permissions::OWNER) {
        $restaurant->update(['owner_id' => $user->id]);
    }

    return $user;
}

function p4Register(array $extra = []): array
{
    return array_merge([
        'restaurant_name' => 'Burger Barn', 'name' => 'Ada', 'email' => 'ada'.uniqid().'@example.com',
        'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
    ], $extra);
}

describe('registration with a plan', function () {
    it('shows the active plans and preselects the requested one', function () {
        $a = p4Plan(['name' => 'Starter']);
        $b = p4Plan(['name' => 'Gold']);
        p4Plan(['name' => 'Hidden', 'is_active' => false]);

        $html = $this->get('/register?plan='.$b->slug)->assertOk()->assertSee('Starter')->assertSee('Gold')->assertDontSee('Hidden')->getContent();

        expect($html)->toMatch('/value="'.preg_quote($b->slug).'"[^>]*checked/');
    });

    it('starts a trial when the plan has one', function () {
        $plan = p4Plan(['trial_days' => 14]);

        $this->post('/register', p4Register(['plan' => $plan->slug]))->assertRedirect(route('verification.notice'));

        $restaurant = Restaurant::latest('id')->first();
        $sub = app(SubscriptionService::class)->current($restaurant);
        expect($sub->status)->toBe('trialing')->and($sub->plan_id)->toBe($plan->id)->and($restaurant->trial_ends_at)->not->toBeNull();
    });

    it('activates a free plan straight away', function () {
        $plan = p4Plan(['interval' => 'free', 'price' => 0]);

        $this->post('/register', p4Register(['plan' => $plan->slug]));

        expect(app(SubscriptionService::class)->current(Restaurant::latest('id')->first())->plan_id)->toBe($plan->id);
    });

    it('sends a paid plan without trial to checkout after e-mail verification', function () {
        $plan = p4Plan();

        $this->post('/register', p4Register(['plan' => $plan->slug]))->assertSessionHas('url.intended', route('billing.checkout', $plan->slug));

        expect(app(SubscriptionService::class)->current(Restaurant::latest('id')->first()))->toBeNull();
    });

    it('rejects unknown or inactive plans and still allows signing up without one', function () {
        $inactive = p4Plan(['is_active' => false]);

        $this->post('/register', p4Register(['plan' => 'nope']))->assertSessionHasErrors('plan');
        $this->post('/register', p4Register(['plan' => $inactive->slug]))->assertSessionHasErrors('plan');
        $this->post('/register', p4Register())->assertRedirect(route('verification.notice'));
    });
});

describe('onboarding wizard', function () {
    it('sends a new owner to the wizard once, then to the dashboard', function () {
        $owner = p4Staff(onboarded: false);

        $this->actingAs($owner)->get('/dashboard')->assertRedirect(route('onboarding.show'));
        $this->get(route('onboarding.show'))->assertOk()->assertSee(__('onboarding.step_profile'));

        $this->post(route('onboarding.finish'))->assertRedirect(route('dashboard'));
        expect($owner->restaurant->fresh()->isOnboarded())->toBeTrue();

        $this->get('/dashboard')->assertOk();
        $this->get(route('onboarding.show'))->assertRedirect(route('dashboard'));
    });

    it('saves the profile and moves to branding', function () {
        $owner = p4Staff(onboarded: false);

        $this->actingAs($owner)->post(route('onboarding.profile'), [
            'name' => 'Cafe Noir', 'phone' => '+90 555 123 45 67', 'address' => '1 Main St', 'city' => 'Izmir', 'country' => 'Turkey',
            'currency_code' => 'USD', 'timezone' => 'Europe/Istanbul', 'locale' => 'en',
        ])->assertRedirect(route('onboarding.show', ['step' => 2]));

        $r = $owner->restaurant->fresh();
        expect($r->name)->toBe('Cafe Noir')->and($r->timezone)->toBe('Europe/Istanbul')->and($r->city)->toBe('Izmir');
    });

    it('validates the profile', function () {
        $owner = p4Staff(onboarded: false);

        $this->actingAs($owner)->post(route('onboarding.profile'), [
            'name' => '', 'phone' => 'call me', 'currency_code' => 'XXX', 'timezone' => 'Mars/Base', 'locale' => 'zz',
        ])->assertSessionHasErrors(['name', 'phone', 'currency_code', 'timezone', 'locale']);
    });

    it('saves colour and logo, and rejects bad input', function () {
        Storage::fake('public');
        $owner = p4Staff(onboarded: false);

        $this->actingAs($owner)->post(route('onboarding.branding'), ['color' => 'red'])->assertSessionHasErrors('color');
        $this->post(route('onboarding.branding'), ['color' => '#112233', 'logo' => UploadedFile::fake()->create('x.txt', 5, 'text/plain')])->assertSessionHasErrors('logo');

        $this->post(route('onboarding.branding'), ['color' => '#AABBCC', 'logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
            ->assertRedirect(route('onboarding.show', ['step' => 3]));

        $r = $owner->restaurant->fresh();
        expect($r->brandColor())->toBe('#aabbcc')->and($r->logo)->not->toBeNull();
        Storage::disk('public')->assertExists($r->logo->path);
    });

    it('keeps staff without settings permission out', function () {
        $restaurant = p4Staff()->restaurant;
        $waiter = p4Staff(Permissions::WAITER, $restaurant);

        $this->actingAs($waiter)->get(route('onboarding.show'))->assertForbidden();
        $this->get(route('restaurant.settings'))->assertForbidden();
        $this->get('/dashboard')->assertOk(); // never redirected into a wizard they cannot use
    });

    it('lets the owner edit the profile later', function () {
        $owner = p4Staff();

        $this->actingAs($owner)->get(route('restaurant.settings'))->assertOk()->assertSee('Cafe');
        $this->put(route('restaurant.settings.profile'), ['name' => 'Renamed', 'timezone' => 'UTC', 'locale' => 'en'])->assertRedirect();

        expect($owner->restaurant->fresh()->name)->toBe('Renamed');
    });
});

describe('subscription panel', function () {
    it('shows plan, usage and plans to the owner only', function () {
        $plan = p4Plan(['name' => 'Gold']);
        $owner = p4Staff();
        app(SubscriptionService::class)->assign($owner->restaurant, $plan, now()->addMonth());

        $this->actingAs($owner)->get(route('billing.index'))->assertOk()->assertSee('Gold')->assertSee(__('billing.usage'));

        $manager = p4Staff(Permissions::MANAGER, $owner->restaurant);
        $this->actingAs($manager)->get(route('billing.index'))->assertForbidden();
        auth()->logout();
        $this->get(route('billing.index'))->assertRedirect(route('login'));
    });

    it('switches to a free plan or a trial immediately, and sends paid plans to checkout', function () {
        $free = p4Plan(['interval' => 'free', 'price' => 0, 'name' => 'Free']);
        $trial = p4Plan(['trial_days' => 7, 'name' => 'Trial']);
        $paid = p4Plan(['name' => 'Paid']);
        $owner = p4Staff();
        $subs = app(SubscriptionService::class);

        $this->actingAs($owner)->post(route('billing.select', $free->slug))->assertRedirect(route('billing.index'));
        expect($subs->current($owner->restaurant)->plan_id)->toBe($free->id);

        $this->post(route('billing.select', $trial->slug))->assertRedirect(route('billing.index'));
        expect($subs->current($owner->restaurant)->status)->toBe('trialing');

        $this->post(route('billing.select', $paid->slug))->assertRedirect(route('billing.checkout', $paid->slug));
        $this->post(route('billing.select', $trial->slug))->assertRedirect(route('billing.index')); // already on it
        $this->post(route('billing.select', 'ghost'))->assertNotFound();
    });

    it('does not offer a second trial of the same plan', function () {
        $trial = p4Plan(['trial_days' => 7]);
        $free = p4Plan(['interval' => 'free', 'price' => 0]);
        $owner = p4Staff();

        $this->actingAs($owner)->post(route('billing.select', $trial->slug));
        $this->post(route('billing.select', $free->slug));

        $this->post(route('billing.select', $trial->slug))->assertRedirect(route('billing.checkout', $trial->slug));
    });

    it('runs a bank-transfer checkout with a coupon and shows the instructions', function () {
        app(GatewayManager::class)->save('bank_transfer', true, ['instructions' => 'IBAN TR00 ref :reference']);
        Coupon::create(['code' => 'TEN', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
        $plan = p4Plan(['price' => 50]);
        $owner = p4Staff();

        $this->actingAs($owner)->get(route('billing.checkout', $plan->slug))->assertOk()->assertSee('Bank')->assertSee('50.00 USD');

        $response = $this->post(route('billing.start', $plan->slug), ['gateway' => 'bank_transfer', 'coupon' => 'ten'])->assertRedirect(route('billing.index'));

        $invoice = Invoice::latest('id')->first();
        expect($invoice->status)->toBe('open')->and((float) $invoice->total)->toBe(45.0);
        $response->assertSessionHas('instructions.invoice', $invoice->number);

        $this->get(route('billing.index'))->assertSee($invoice->number)->assertSee('IBAN TR00 ref '.$invoice->number);
    });

    it('rejects an unavailable gateway and a bad coupon without leaving an open invoice', function () {
        app(GatewayManager::class)->save('bank_transfer', true, ['instructions' => 'IBAN']);
        $plan = p4Plan();
        $owner = p4Staff();

        $this->actingAs($owner)->post(route('billing.start', $plan->slug), ['gateway' => 'stripe'])->assertSessionHasErrors('gateway');
        $this->post(route('billing.start', $plan->slug), ['gateway' => 'bank_transfer', 'coupon' => 'NOPE'])->assertSessionHasErrors('checkout');

        expect(Invoice::allTenants()->where('status', 'open')->count())->toBe(0);
    });

    it('previews coupons as JSON', function () {
        Coupon::create(['code' => 'HALF', 'type' => 'percent', 'value' => 50, 'is_active' => true]);
        $plan = p4Plan(['price' => 40]);
        $owner = p4Staff();

        $this->actingAs($owner)->getJson(route('billing.preview', $plan->slug).'?coupon=half')->assertOk()->assertJsonPath('lines.total', '20.00 USD');
        $this->getJson(route('billing.preview', $plan->slug).'?coupon=nope')->assertStatus(422)->assertJsonPath('ok', false);
    });

    it('cancels at period end, only once', function () {
        $owner = p4Staff();
        $sub = app(SubscriptionService::class)->assign($owner->restaurant, p4Plan(), now()->addMonth());

        $this->actingAs($owner)->post(route('billing.cancel'))->assertRedirect();
        $sub->refresh();
        expect($sub->canceled_at)->not->toBeNull()->and($sub->status)->toBe('active');

        $this->post(route('billing.cancel'))->assertNotFound();
    });

    it('serves invoice PDFs to their owner only', function () {
        $plan = p4Plan();
        $owner = p4Staff();
        $other = p4Staff();
        $mine = app(InvoiceService::class)->create($owner->restaurant, $plan);
        $theirs = app(InvoiceService::class)->create($other->restaurant, $plan);

        $this->actingAs($owner)->get(route('billing.invoice.pdf', $mine->id))->assertOk()->assertDownload($mine->number.'.pdf');
        $this->get(route('billing.invoice.pdf', $theirs->id))->assertNotFound();
        $this->get(route('billing.index'))->assertSee($mine->number)->assertDontSee($theirs->number);
    });
});

describe('usage and notices', function () {
    it('reports usage state against the plan limits', function () {
        $owner = p4Staff();
        app(SubscriptionService::class)->assign($owner->restaurant, p4Plan(['limits' => ['products' => 10, 'tables' => null, 'staff' => 0]]), now()->addMonth());
        UsageRegistry::register('products', fn () => 8);
        UsageRegistry::register('staff', fn () => 0);

        $rows = collect(app(UsageReport::class)->for($owner->restaurant))->keyBy('key');

        expect($rows['products'])->toMatchArray(['used' => 8, 'limit' => 10, 'percent' => 80, 'state' => 'warning'])
            ->and($rows['tables']['state'])->toBe('unlimited')
            ->and($rows['staff']['state'])->toBe('full')
            ->and($rows['branches']['state'])->toBe('unlimited');

        UsageRegistry::register('products', fn () => 10);
        expect(collect(app(UsageReport::class)->for($owner->restaurant))->firstWhere('key', 'products')['state'])->toBe('full');
    });

    it('warns about no plan, an ending trial, an overdue payment and limits', function () {
        $owner = p4Staff();
        $notices = app(PlanNotices::class);

        expect($notices->for($owner->restaurant)[0]['text'])->toBe(__('billing.notice_no_plan'));

        $sub = app(SubscriptionService::class)->assign($owner->restaurant, p4Plan(), now()->addDays(2));
        expect($notices->for($owner->restaurant)[0]['text'])->toContain('2');

        $sub->update(['ends_at' => now()->addDays(20), 'status' => 'past_due']);
        expect($notices->for($owner->restaurant)[0]['text'])->toBe(__('billing.notice_past_due'));

        $sub->update(['status' => 'active']);
        expect($notices->for($owner->restaurant))->toBe([]);

        UsageRegistry::register('products', fn () => 9);
        expect($notices->for($owner->restaurant)[0]['text'])->toContain('9 of 10');
    });

    it('shows the banners to the owner on panel pages but not to other roles', function () {
        $owner = p4Staff();
        $manager = p4Staff(Permissions::MANAGER, $owner->restaurant);

        $this->actingAs($owner)->get('/dashboard')->assertSee(__('billing.notice_no_plan'));
        $this->actingAs($manager)->get('/dashboard')->assertDontSee(__('billing.notice_no_plan'));
    });
});
