<?php

use App\Models\User;
use App\Modules\Admin\Services\DashboardService;
use App\Modules\Admin\Support\ChartScale;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['password' => 'long-enough-1']);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->admin->assignRole(Permissions::SUPER_ADMIN);
});

function owner(Restaurant $restaurant): User
{
    $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'Olive Owner']);
    $restaurant->update(['owner_id' => $user->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($restaurant->id);
    $user->assignRole(Permissions::OWNER);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $user;
}

function newPlan(array $o = []): Plan
{
    return Plan::create(array_merge(['name' => 'Starter', 'slug' => 'starter-'.uniqid(), 'interval' => 'monthly', 'price' => 20, 'currency_code' => 'USD'], $o));
}

describe('access', function () {
    it('is closed to guests and to restaurant staff', function (string $url) {
        $this->get($url)->assertRedirect(route('login'));

        $r = Restaurant::create(['name' => 'R', 'slug' => 'r']);
        $this->actingAs(owner($r))->get($url)->assertForbidden();
    })->with(['/admin', '/admin/restaurants', '/admin/plans', '/admin/subscriptions', '/admin/invoices', '/admin/coupons', '/admin/settings/billing']);

    it('opens every screen for the super admin', function (string $url) {
        $this->actingAs($this->admin)->get($url)->assertOk();
    })->with(['/admin', '/admin/restaurants', '/admin/plans', '/admin/plans/create', '/admin/subscriptions', '/admin/invoices', '/admin/coupons', '/admin/coupons/create', '/admin/settings/billing', '/admin/updates']);
});

describe('dashboard', function () {
    it('computes MRR from monthly and yearly subscriptions only, in the platform currency', function () {
        $subs = app(SubscriptionService::class);
        $subs->assign(Restaurant::create(['name' => 'A', 'slug' => 'a']), newPlan(['price' => 30]));
        $subs->assign(Restaurant::create(['name' => 'B', 'slug' => 'b']), newPlan(['interval' => 'yearly', 'price' => 120]));
        $subs->assign(Restaurant::create(['name' => 'C', 'slug' => 'c']), newPlan(['interval' => 'lifetime', 'price' => 999]));
        $subs->assign(Restaurant::create(['name' => 'D', 'slug' => 'd']), newPlan(['price' => 500, 'currency_code' => 'EUR']));
        $subs->startTrial(Restaurant::create(['name' => 'E', 'slug' => 'e']), newPlan(['price' => 77, 'trial_days' => 7]));

        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('40.00 USD');
    });

    it('reports signups and monthly revenue with zero-filled series', function () {
        Restaurant::create(['name' => 'New', 'slug' => 'new']);
        $r = Restaurant::create(['name' => 'Payer', 'slug' => 'payer']);
        $svc = app(InvoiceService::class);
        $svc->markPaid($svc->create($r, newPlan(['price' => 50])));

        $dashboard = app(DashboardService::class);
        $signups = $dashboard->signupsByDay(30);
        $revenue = $dashboard->revenueByMonth(12);

        expect($signups['values'])->toHaveCount(30)->and(array_sum($signups['values']))->toBe(2)
            ->and($revenue['values'])->toHaveCount(12)->and(end($revenue['values']))->toBe(50.0)
            ->and(array_sum($revenue['values']))->toBe(50.0);
    });

    it('picks clean axis ticks', function () {
        expect(ChartScale::nice(0))->toMatchArray(['max' => 1.0])
            ->and(ChartScale::nice(950)['ticks'])->toBe([0.0, 500.0, 1000.0])
            ->and(ChartScale::nice(7)['max'])->toBe(8.0)
            ->and(ChartScale::nice(1, 4, integer: true)['ticks'])->toBe([0.0, 1.0])
            ->and(ChartScale::nice(3, 4, integer: true)['ticks'])->toBe([0.0, 1.0, 2.0, 3.0]);
    });
});

describe('restaurants', function () {
    it('searches by name, slug or owner email and filters by status and plan', function () {
        $a = Restaurant::create(['name' => 'Alpha Grill', 'slug' => 'alpha']);
        $b = Restaurant::create(['name' => 'Beta Bistro', 'slug' => 'beta', 'status' => 'suspended']);
        owner($b)->update(['email' => 'chef@beta.test']);
        $plan = newPlan();
        app(SubscriptionService::class)->assign($a, $plan);

        $this->actingAs($this->admin);
        $this->get('/admin/restaurants?q=Alpha')->assertSee('Alpha Grill')->assertDontSee('Beta Bistro');
        $this->get('/admin/restaurants?q=chef@beta')->assertSee('Beta Bistro')->assertDontSee('Alpha Grill');
        $this->get('/admin/restaurants?status=suspended')->assertSee('Beta Bistro')->assertDontSee('Alpha Grill');
        $this->get('/admin/restaurants?plan='.$plan->id)->assertSee('Alpha Grill')->assertDontSee('Beta Bistro');
    });

    it('treats LIKE wildcards in the search as plain text', function () {
        Restaurant::create(['name' => 'Alpha', 'slug' => 'alpha']);

        $this->actingAs($this->admin)->get('/admin/restaurants?q=%25')->assertDontSee('Alpha');
    });

    it('suspends and reactivates, locking staff out while suspended', function () {
        $r = Restaurant::create(['name' => 'Locked', 'slug' => 'locked']);
        $staff = owner($r);

        $this->actingAs($this->admin)->post("/admin/restaurants/{$r->id}/suspend")->assertRedirect();
        expect($r->fresh()->isSuspended())->toBeTrue();

        auth()->logout();
        $this->actingAs($staff)->get('/dashboard')->assertForbidden();
        $this->get('/r/locked')->assertNotFound();

        $this->actingAs($this->admin)->post("/admin/restaurants/{$r->id}/unsuspend");
        expect($r->fresh()->isSuspended())->toBeFalse();
    });

    it('soft deletes, locks out staff, and restores', function () {
        $r = Restaurant::create(['name' => 'Gone', 'slug' => 'gone']);
        $staff = owner($r);

        $this->actingAs($this->admin)->delete("/admin/restaurants/{$r->id}")->assertRedirect(route('admin.restaurants.index'));
        expect(Restaurant::find($r->id))->toBeNull()->and(Restaurant::withTrashed()->find($r->id))->not->toBeNull();

        // A user of a deleted restaurant must not slip through as a platform user.
        auth()->logout();
        $this->actingAs($staff->fresh())->get('/dashboard')->assertForbidden();

        $this->actingAs($this->admin)->post("/admin/restaurants/{$r->id}/restore")->assertRedirect();
        expect(Restaurant::find($r->id))->not->toBeNull();
    });

    it('validates slug uniqueness and format on update', function () {
        $a = Restaurant::create(['name' => 'A', 'slug' => 'taken']);
        $b = Restaurant::create(['name' => 'B', 'slug' => 'free']);

        $this->actingAs($this->admin)->put("/admin/restaurants/{$b->id}", ['name' => 'B', 'slug' => 'taken', 'locale' => 'en'])->assertSessionHasErrors('slug');
        $this->put("/admin/restaurants/{$b->id}", ['name' => 'B', 'slug' => 'Bad Slug!', 'locale' => 'en'])->assertSessionHasErrors('slug');
        $this->put("/admin/restaurants/{$b->id}", ['name' => 'B2', 'slug' => 'fresh', 'locale' => 'en'])->assertSessionHasNoErrors();
        expect($b->fresh()->slug)->toBe('fresh');
    });
});

describe('impersonation', function () {
    it('logs in as the owner, shows a banner, and returns to the admin', function () {
        $r = Restaurant::create(['name' => 'Target', 'slug' => 'target']);
        $owner = owner($r);

        $this->actingAs($this->admin)->post("/admin/restaurants/{$r->id}/impersonate")->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);

        $this->get('/dashboard')->assertOk()->assertSee(__('admin.impersonate.stop'));
        $this->get('/admin')->assertForbidden(); // the owner has no admin rights

        $this->post('/impersonation/stop')->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->admin);
        $this->get('/dashboard')->assertDontSee(__('admin.impersonate.stop'));
    });

    it('refuses suspended restaurants and ownerless ones, and cannot be nested', function () {
        $suspended = Restaurant::create(['name' => 'S', 'slug' => 's', 'status' => 'suspended']);
        owner($suspended);
        $ownerless = Restaurant::create(['name' => 'O', 'slug' => 'o']);

        $this->actingAs($this->admin);
        $this->post("/admin/restaurants/{$suspended->id}/impersonate")->assertStatus(422);
        $this->post("/admin/restaurants/{$ownerless->id}/impersonate")->assertStatus(422);
        $this->assertAuthenticatedAs($this->admin);
    });

    it('cannot be started by restaurant staff and stop does nothing without a session', function () {
        $r = Restaurant::create(['name' => 'R', 'slug' => 'r']);
        $staff = owner($r);

        $this->actingAs($staff)->post("/admin/restaurants/{$r->id}/impersonate")->assertForbidden();
        $this->post('/impersonation/stop')->assertNotFound();
    });
});

describe('plans', function () {
    $valid = fn (array $o = []) => array_merge([
        'name' => 'Pro', 'slug' => 'pro', 'interval' => 'monthly', 'price' => '29.90', 'currency_code' => 'USD',
        'trial_days' => 14, 'limits' => ['products' => '100', 'tables' => '', 'bogus' => '5'], 'features' => ['custom_domain' => '1', 'bogus' => '1'], 'is_active' => '1',
    ], $o);

    it('stores limits (blank = unlimited) and feature switches, dropping unknown keys', function () use ($valid) {
        $this->actingAs($this->admin)->post('/admin/plans', $valid())->assertRedirect(route('admin.plans.index'));

        $plan = Plan::firstWhere('slug', 'pro');
        expect($plan->limit('products'))->toBe(100)
            ->and($plan->limit('tables'))->toBeNull()
            ->and($plan->limits)->not->toHaveKey('bogus')
            ->and($plan->hasFeature('custom_domain'))->toBeTrue()
            ->and($plan->hasFeature('reservations'))->toBeFalse()
            ->and($plan->features)->not->toHaveKey('bogus');
    });

    it('forces free plans to price zero and validates input', function () use ($valid) {
        $this->actingAs($this->admin)->post('/admin/plans', $valid(['slug' => 'free', 'interval' => 'free', 'price' => '9']));
        expect((float) Plan::firstWhere('slug', 'free')->price)->toBe(0.0);

        $this->post('/admin/plans', $valid(['slug' => 'free']))->assertSessionHasErrors('slug');
        $this->post('/admin/plans', $valid(['slug' => 'x', 'price' => '-1']))->assertSessionHasErrors('price');
        $this->post('/admin/plans', $valid(['slug' => 'y', 'interval' => 'weekly']))->assertSessionHasErrors('interval');
    });

    it('deactivates instead of deleting a plan that has subscribers', function () {
        $used = newPlan();
        app(SubscriptionService::class)->assign(Restaurant::create(['name' => 'A', 'slug' => 'a']), $used);
        $unused = newPlan();

        $this->actingAs($this->admin)->delete("/admin/plans/{$used->id}");
        $this->delete("/admin/plans/{$unused->id}");

        expect($used->fresh()->is_active)->toBeFalse()->and(Plan::find($used->id))->not->toBeNull()
            ->and(Plan::find($unused->id))->toBeNull();
    });
});

describe('subscriptions and invoices', function () {
    it('assigns a plan manually and can record a paid invoice', function () {
        $r = Restaurant::create(['name' => 'R', 'slug' => 'r']);
        $plan = newPlan(['price' => 25]);

        $this->actingAs($this->admin)->post('/admin/subscriptions', ['restaurant_id' => $r->id, 'plan_id' => $plan->id, 'create_invoice' => '1'])->assertSessionHasNoErrors();

        $sub = Subscription::allTenants()->where('restaurant_id', $r->id)->first();
        $invoice = Invoice::allTenants()->where('restaurant_id', $r->id)->first();
        expect($sub->status)->toBe('active')->and($sub->gateway)->toBe('manual')
            ->and($invoice->status)->toBe('paid')->and((float) $invoice->total)->toBe(25.0);
    });

    it('rejects assigning to deleted restaurants or past end dates', function () {
        $r = Restaurant::create(['name' => 'R', 'slug' => 'r']);
        $plan = newPlan();
        $this->actingAs($this->admin);

        $this->post('/admin/subscriptions', ['restaurant_id' => $r->id, 'plan_id' => $plan->id, 'ends_at' => now()->subDay()->toDateString()])->assertSessionHasErrors('ends_at');
        $r->delete();
        $this->post('/admin/subscriptions', ['restaurant_id' => $r->id, 'plan_id' => $plan->id])->assertSessionHasErrors('restaurant_id');
    });

    it('renews and cancels', function () {
        $r = Restaurant::create(['name' => 'R', 'slug' => 'r']);
        $sub = app(SubscriptionService::class)->assign($r, newPlan());
        $oldEnd = $sub->ends_at->copy();

        $this->actingAs($this->admin)->post("/admin/subscriptions/{$sub->id}/renew");
        expect($sub->fresh()->ends_at->gt($oldEnd))->toBeTrue();

        $this->post("/admin/subscriptions/{$sub->id}/cancel", ['immediately' => '1']);
        expect($sub->fresh()->status)->toBe('canceled');
    });

    it('lists, downloads, pays and voids invoices but never voids a paid one', function () {
        $r = Restaurant::create(['name' => 'R', 'slug' => 'r']);
        $svc = app(InvoiceService::class);
        $open = $svc->create($r, newPlan(['price' => 10]));
        $paid = $svc->markPaid($svc->create($r, newPlan(['price' => 10])));
        $this->actingAs($this->admin);

        $this->get('/admin/invoices')->assertSee($open->number)->assertSee($paid->number);
        $pdf = $this->get("/admin/invoices/{$open->id}/pdf")->assertOk();
        expect(substr($pdf->getContent(), 0, 4))->toBe('%PDF');

        $this->post("/admin/invoices/{$paid->id}/void")->assertStatus(422);
        $this->post("/admin/invoices/{$open->id}/paid");
        expect($open->fresh()->status)->toBe('paid');
    });
});

describe('coupons and billing settings', function () {
    it('creates coupons with an upper-cased code and validates ranges', function () {
        $this->actingAs($this->admin);
        $plan = newPlan();

        $this->post('/admin/coupons', ['code' => 'save20', 'type' => 'percent', 'value' => '20', 'plan_ids' => [$plan->id]])->assertSessionHasNoErrors();
        $coupon = Coupon::firstWhere('code', 'SAVE20');
        expect($coupon->plan_ids)->toBe([$plan->id])->and($coupon->is_active)->toBeFalse(); // unchecked box = inactive

        $this->post('/admin/coupons', ['code' => 'BIG', 'type' => 'percent', 'value' => '150'])->assertSessionHasErrors('value');
        $this->post('/admin/coupons', ['code' => 'save20', 'type' => 'fixed', 'value' => '5'])->assertSessionHasErrors('code');
        $this->post('/admin/coupons', ['code' => 'bad code!', 'type' => 'fixed', 'value' => '5'])->assertSessionHasErrors('code');
        $this->post('/admin/coupons', ['code' => 'LATE', 'type' => 'fixed', 'value' => '5', 'starts_at' => '2030-01-02', 'expires_at' => '2030-01-01'])->assertSessionHasErrors('expires_at');
    });

    it('saves tax and company settings that change invoice amounts', function () {
        $this->actingAs($this->admin)->put('/admin/settings/billing', [
            'currency' => 'USD', 'tax_name' => 'KDV', 'tax_rate' => '20', 'company_name' => 'Acme Ltd', 'prices_include_tax' => '1',
        ])->assertSessionHasNoErrors();

        $settings = app(SettingsService::class);
        expect($settings->get('billing.tax_name'))->toBe('KDV')
            ->and($settings->get('billing.prices_include_tax'))->toBe('1');

        $invoice = app(InvoiceService::class)->create(Restaurant::create(['name' => 'R', 'slug' => 'r']), newPlan(['price' => 120]));
        expect((float) $invoice->tax_amount)->toBe(20.0)->and((float) $invoice->total)->toBe(120.0)->and($invoice->billing['seller']['name'])->toBe('Acme Ltd');

        $this->put('/admin/settings/billing', ['currency' => 'USD', 'tax_rate' => '101'])->assertSessionHasErrors('tax_rate');
    });
});

describe('edit forms', function () {
    it('come pre-filled with the stored values so saving never blanks a field', function () {
        $r = Restaurant::create(['name' => 'Prefilled Pizza', 'slug' => 'prefilled', 'locale' => 'tr']);
        $plan = newPlan(['name' => 'Gold', 'slug' => 'gold', 'price' => 49.5, 'trial_days' => 9, 'limits' => ['products' => 123]]);
        $coupon = Coupon::create(['code' => 'HELLO', 'type' => 'percent', 'value' => 15, 'max_uses' => 7]);
        $this->actingAs($this->admin);

        $this->get("/admin/restaurants/{$r->id}")->assertSee('value="Prefilled Pizza"', false)->assertSee('value="prefilled"', false)->assertSee('value="tr"', false);
        $this->get("/admin/plans/{$plan->id}/edit")->assertSee('value="Gold"', false)->assertSee('value="49.50"', false)
            ->assertSee('value="9"', false)->assertSee('value="123"', false);
        $this->get("/admin/coupons/{$coupon->id}/edit")->assertSee('value="HELLO"', false)->assertSee('value="15.00"', false)->assertSee('value="7"', false);
    });

    it('keeps what the user typed after a validation error, including array-style fields', function () {
        $this->actingAs($this->admin)->from('/admin/plans/create')
            ->post('/admin/plans', ['name' => 'Typed Name', 'slug' => 'Bad Slug', 'interval' => 'monthly', 'price' => '5', 'currency_code' => 'USD', 'limits' => ['products' => '77']])
            ->assertRedirect('/admin/plans/create');

        $this->get('/admin/plans/create')->assertSee('value="Typed Name"', false)->assertSee('value="77"', false);
    });
});
