<?php

use App\Models\User;
use App\Modules\Affiliate\Models\Referral;
use App\Modules\Affiliate\Services\AffiliateService;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Services\PaymentProcessor;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    app(SettingsService::class)->set('affiliate.enabled', '1');
    app(SettingsService::class)->set('affiliate.percent', '20');
    app(SettingsService::class)->set('billing.currency', 'USD');
});

function afRestaurant(string $name = 'Af'): Restaurant
{
    $r = Restaurant::create(['name' => $name, 'slug' => strtolower($name).uniqid(), 'locale' => 'en', 'currency_code' => 'USD']);
    $u = User::factory()->create(['restaurant_id' => $r->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    $u->assignRole(Permissions::OWNER);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $r->forceFill(['owner_id' => $u->id])->save();

    return $r->fresh();
}

function afPlan(float $price = 50): Plan
{
    return Plan::create(['name' => 'P'.$price, 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => $price, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
}

function afRegister($test, array $over = []): User
{
    $test->post('/register', array_merge(['restaurant_name' => 'Newbie', 'name' => 'New Owner', 'email' => 'new'.uniqid().'@example.com', 'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1'], $over))->assertSessionHasNoErrors();

    return User::latest('id')->first();
}

it('gives every restaurant one stable referral link', function () {
    $r = afRestaurant();
    $svc = app(AffiliateService::class);
    $code = $svc->codeFor($r);
    expect($code)->toMatch('/^[A-Z0-9]{8}$/')->and($svc->codeFor($r->fresh()))->toBe($code)->and($svc->link($r))->toContain('ref='.$code);
});

it('remembers the link through sign-up and ignores unknown codes', function () {
    $referrer = afRestaurant('Referrer');
    $code = app(AffiliateService::class)->codeFor($referrer);

    $this->get('/register?ref='.$code)->assertOk();
    $user = afRegister($this);
    $ref = Referral::first();
    expect($ref->referrer_id)->toBe($referrer->id)->and($ref->referred_id)->toBe($user->restaurant_id)->and($ref->status)->toBe('pending');

    auth()->logout();
    $this->get('/register?ref=NOSUCHCODE')->assertOk();
    afRegister($this);
    expect(Referral::count())->toBe(1);
});

it('does nothing when the program is off', function () {
    app(SettingsService::class)->set('affiliate.enabled', '0');
    $referrer = afRestaurant('Referrer');
    $code = $referrer->forceFill(['referral_code' => 'ABCD1234'])->saveQuietly() ? 'ABCD1234' : '';
    $this->get('/register?ref='.$code)->assertOk();
    afRegister($this);
    expect(Referral::count())->toBe(0);
    $this->actingAs($referrer->owner)->get(route('referrals.index'))->assertNotFound();
});

it('rewards the referrer once, with a share of the first paid invoice', function () {
    $referrer = afRestaurant('Referrer');
    $new = afRestaurant('Newcomer');
    app(AffiliateService::class)->attach($new, app(AffiliateService::class)->codeFor($referrer));

    $invoice = app(InvoiceService::class)->create($new, afPlan(50));
    app(PaymentProcessor::class)->settle($invoice);
    expect((float) $referrer->fresh()->billing_credit)->toBe(10.0)->and(Referral::first())->status->toBe('rewarded')->reward->toBe('10.00');

    // A second payment of the newcomer earns nothing more.
    app(PaymentProcessor::class)->settle(app(InvoiceService::class)->create($new, afPlan(50)));
    expect((float) $referrer->fresh()->billing_credit)->toBe(10.0);
});

it('takes the credit off the referrer’s next invoice and gives it back if that invoice is voided', function () {
    $referrer = afRestaurant('Referrer');
    $referrer->forceFill(['billing_credit' => 10])->save();
    $svc = app(InvoiceService::class);

    $invoice = $svc->create($referrer, afPlan(50));
    expect((float) $invoice->total)->toBe(40.0)->and((float) $invoice->credit_used)->toBe(10.0)->and((float) $referrer->fresh()->billing_credit)->toBe(0.0)->and($invoice->items)->toHaveCount(2);

    $svc->void($invoice);
    expect((float) $referrer->fresh()->billing_credit)->toBe(10.0)->and((float) $invoice->fresh()->credit_used)->toBe(0.0);
});

it('covers a small invoice completely and keeps the rest of the credit', function () {
    $r = afRestaurant();
    $r->forceFill(['billing_credit' => 30])->save();
    $invoice = app(InvoiceService::class)->create($r, afPlan(12));
    expect((float) $invoice->total)->toBe(0.0)->and($invoice->status)->toBe('paid')->and((float) $r->fresh()->billing_credit)->toBe(18.0);
});

it('never spends credit on an invoice in another currency', function () {
    $r = afRestaurant();
    $r->forceFill(['billing_credit' => 10])->save();
    $eur = afPlan(50);
    $eur->update(['currency_code' => 'EUR']);
    expect((float) app(InvoiceService::class)->create($r, $eur)->total)->toBe(50.0)->and((float) $r->fresh()->billing_credit)->toBe(10.0);
});

it('shows the owner their link, referrals and credit', function () {
    $referrer = afRestaurant('Referrer');
    $new = afRestaurant('Joiner');
    app(AffiliateService::class)->attach($new, app(AffiliateService::class)->codeFor($referrer));
    $referrer->forceFill(['billing_credit' => 7.5])->save();

    $this->actingAs($referrer->owner)->get(route('referrals.index'))->assertOk()->assertSee('Joiner')->assertSee('7.50')->assertSee('ref='.$referrer->referral_code);
});
