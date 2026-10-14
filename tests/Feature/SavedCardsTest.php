<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\SavedCard;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Payments\GatewayManager;
use App\Modules\Billing\Payments\PaymentNotification;
use App\Modules\Billing\Services\CheckoutService;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Services\PaymentProcessor;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    app(GatewayManager::class)->save('stripe', true, ['secret_key' => 'sk_test', 'webhook_secret' => 'whsec_test']);
    Mail::fake();
});

function scPlan(array $o = []): Plan
{
    return Plan::create($o + ['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
}

/** @return array{0: Restaurant, 1: User} */
function scShop(): array
{
    $r = Restaurant::create(['name' => 'Card Cafe', 'slug' => 'sc'.uniqid(), 'locale' => 'en', 'currency_code' => 'USD', 'onboarded_at' => now()]);
    $u = User::factory()->create(['restaurant_id' => $r->id]);
    $r->update(['owner_id' => $u->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $u->assignRole(Permissions::OWNER);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return [$r->refresh(), $u];
}

function scSubscribe(Restaurant $r, Plan $plan, $endsAt): Subscription
{
    return app(SubscriptionService::class)->assign($r, $plan, $endsAt);
}

function scCard(Restaurant $r, array $over = []): SavedCard
{
    $card = (new SavedCard)->forceFill($over + ['restaurant_id' => $r->id, 'gateway' => 'stripe', 'customer_id' => 'cus_1', 'method_id' => 'pm_1', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => (int) now()->addYear()->format('Y')]);
    $card->save();

    return $card;
}

function scStripe(string $mode = 'ok'): void
{
    Http::fake([
        'api.stripe.com/v1/payment_intents/pi_*' => Http::response(['id' => 'pi_first', 'customer' => 'cus_9', 'payment_method' => ['id' => 'pm_9', 'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 5, 'exp_year' => 2031]]]),
        'api.stripe.com/v1/payment_intents' => $mode === 'ok'
            ? fn (Request $req) => Http::response(['id' => 'pi_renew_'.uniqid(), 'status' => 'succeeded', 'amount_received' => (int) $req['amount']])
            : Http::response(['error' => ['code' => 'card_declined', 'message' => 'Your card was declined.', 'payment_intent' => ['id' => 'pi_declined']]], 402),
        'api.stripe.com/v1/checkout/sessions' => fn () => Http::response(['id' => 'cs_'.uniqid(), 'url' => 'https://checkout.stripe.test/c/x']),
    ]);
}

it('asks Stripe to keep the card only when the restaurant ticked the box', function () {
    scStripe();
    [$r] = scShop();
    $plan = scPlan();

    app(CheckoutService::class)->start($r, $plan, 'stripe', null, saveCard: false);
    Http::assertSent(fn (Request $q) => str_contains($q->url(), 'checkout/sessions') && ! isset($q['customer_creation']) && ! isset($q['payment_intent_data']));

    app(CheckoutService::class)->start($r, $plan, 'stripe', null, saveCard: true);
    Http::assertSent(fn (Request $q) => str_contains($q->url(), 'checkout/sessions') && ($q['customer_creation'] ?? null) === 'always' && ($q['payment_intent_data']['setup_future_usage'] ?? null) === 'off_session' && ($q['metadata']['save_card'] ?? null) === '1');
});

it('shows the box at checkout and keeps the card after the payment is booked', function () {
    scStripe();
    [$r, $u] = scShop();
    $plan = scPlan();

    $this->actingAs($u)->get(route('billing.checkout', $plan->slug))->assertOk()->assertSee(__('cards.checkbox'));
    $this->actingAs($u)->get(route('billing.checkout', scPlan(['interval' => 'lifetime', 'price' => 99])->slug))->assertOk()->assertDontSee(__('cards.checkbox'));

    $invoice = app(InvoiceService::class)->create($r, $plan);
    $amount = (int) round($invoice->total * 100);
    app(PaymentProcessor::class)->handle(new PaymentNotification('stripe', $invoice->number, 'pi_first', PaymentNotification::SUCCEEDED, $amount, 'USD', ['save_card' => true]));

    expect($invoice->fresh()->status)->toBe('paid');
    $card = SavedCard::withoutGlobalScopes()->where('restaurant_id', $r->id)->first();
    expect($card->last4)->toBe('4242')->and($card->brand)->toBe('visa')->and($card->customer_id)->toBe('cus_9')->and($card->method_id)->toBe('pm_9')->and($card->auto_renew)->toBeTrue();

    // A payment that did not ask for it keeps nothing.
    [$r2] = scShop();
    $inv2 = app(InvoiceService::class)->create($r2, $plan);
    app(PaymentProcessor::class)->handle(new PaymentNotification('stripe', $inv2->number, 'pi_other', PaymentNotification::SUCCEEDED, $amount, 'USD', ['save_card' => false]));
    expect(SavedCard::withoutGlobalScopes()->where('restaurant_id', $r2->id)->exists())->toBeFalse();

    // The card page appears on the subscription screen and holds no card number.
    $this->actingAs($u)->get(route('billing.index'))->assertOk()->assertSee('Visa')->assertSee('4242')->assertSee(__('cards.on_text'));
    expect(json_encode($card->toArray()))->not->toContain('cus_9')->not->toContain('pm_9');
});

it('renews a plan that ends soon with the saved card, once', function () {
    scStripe();
    [$r] = scShop();
    $plan = scPlan();
    $sub = scSubscribe($r, $plan, now()->addHours(10));
    scCard($r);

    $this->artisan('billing:auto-renew')->assertSuccessful()->expectsOutputToContain('Renewed 1');

    $fresh = $sub->fresh();
    expect($fresh->status)->toBe('active')->and($fresh->ends_at->isAfter(now()->addDays(25)))->toBeTrue();
    $invoice = Invoice::allTenants()->where('restaurant_id', $r->id)->latest('id')->first();
    expect($invoice->status)->toBe('paid')->and(Payment::allTenants()->where('invoice_id', $invoice->id)->where('status', 'succeeded')->exists())->toBeTrue();
    Http::assertSent(fn (Request $q) => str_ends_with($q->url(), '/payment_intents') && $q['customer'] === 'cus_1' && $q['payment_method'] === 'pm_1' && $q['off_session'] === 'true' && $q['confirm'] === 'true'
        && $q->hasHeader('Idempotency-Key', 'renew-'.$invoice->number));
    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'invoice_paid');

    // The period moved on, so the next run finds nothing to do.
    $this->artisan('billing:auto-renew')->assertSuccessful()->expectsOutputToContain('Renewed 0');
    expect(Invoice::allTenants()->where('restaurant_id', $r->id)->count())->toBe(1);
});

it('does not charge when it should not', function () {
    scStripe();
    $plan = scPlan();
    $cases = [
        'far from the end' => fn (Restaurant $r) => [scSubscribe($r, $plan, now()->addDays(10)), scCard($r)],
        'canceled' => fn (Restaurant $r) => [tap(scSubscribe($r, $plan, now()->addHours(5)))->update(['canceled_at' => now()]), scCard($r)],
        'switched off' => fn (Restaurant $r) => [scSubscribe($r, $plan, now()->addHours(5)), scCard($r, ['auto_renew' => false])],
        'card expired' => fn (Restaurant $r) => [scSubscribe($r, $plan, now()->addHours(5)), scCard($r, ['exp_year' => (int) now()->subYear()->format('Y')])],
        'too many failures' => fn (Restaurant $r) => [scSubscribe($r, $plan, now()->addHours(5)), scCard($r, ['failed_attempts' => 3])],
        'free plan' => fn (Restaurant $r) => [scSubscribe($r, scPlan(['interval' => 'free', 'price' => 0]), now()->addHours(5)), scCard($r)],
        'lifetime plan' => fn (Restaurant $r) => [scSubscribe($r, scPlan(['interval' => 'lifetime', 'price' => 50]), null), scCard($r)],
        'no card' => fn (Restaurant $r) => [scSubscribe($r, $plan, now()->addHours(5))],
        'tried today' => fn (Restaurant $r) => [scSubscribe($r, $plan, now()->addHours(5)), scCard($r, ['last_attempt_at' => now()->subHours(2)])],
    ];

    foreach ($cases as $name => $make) {
        [$r] = scShop();
        $make($r);
        $before = Invoice::allTenants()->where('restaurant_id', $r->id)->count();
        $this->artisan('billing:auto-renew')->assertSuccessful();
        expect(Invoice::allTenants()->where('restaurant_id', $r->id)->count())->toBe($before, $name);
    }

    Http::assertNotSent(fn (Request $q) => str_ends_with($q->url(), '/payment_intents'));
});

it('keeps the plan as it is when the card is refused, tells the owner and gives up after three days', function () {
    scStripe('declined');
    [$r] = scShop();
    $plan = scPlan();
    $sub = scSubscribe($r, $plan, now()->addHours(10));
    $endsAt = $sub->fresh()->ends_at;
    $card = scCard($r);

    $this->artisan('billing:auto-renew')->assertSuccessful()->expectsOutputToContain('failed 1');

    expect($sub->fresh()->ends_at->equalTo($endsAt))->toBeTrue()->and($card->fresh()->failed_attempts)->toBe(1)->and($card->fresh()->auto_renew)->toBeTrue();
    expect(Invoice::allTenants()->where('restaurant_id', $r->id)->value('status'))->toBe('void');
    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'card_failed' && $m->hasTo($r->owner->email));

    // Same day: no second try. Next days: tries two and three, then automatic renewal is switched off.
    $this->artisan('billing:auto-renew')->assertSuccessful()->expectsOutputToContain('failed 0');
    foreach ([2, 3] as $day) {
        $this->travel(1)->days();
        $this->artisan('billing:auto-renew')->assertSuccessful();
        expect($card->fresh()->failed_attempts)->toBe($day);
    }
    expect($card->fresh()->auto_renew)->toBeFalse();
    $this->travel(1)->days();
    $this->artisan('billing:auto-renew')->assertSuccessful()->expectsOutputToContain('failed 0');
});

it('lets the owner switch automatic renewal off and on, and remove the card', function () {
    [$r, $u] = scShop();
    [, $other] = scShop();
    scCard($r, ['failed_attempts' => 2]);

    $this->actingAs($u)->post(route('billing.card.auto-renew'))->assertRedirect();
    $card = SavedCard::withoutGlobalScopes()->where('restaurant_id', $r->id)->first();
    expect($card->auto_renew)->toBeFalse();

    $this->actingAs($u)->post(route('billing.card.auto-renew'))->assertRedirect();
    expect($card->fresh()->auto_renew)->toBeTrue()->and($card->fresh()->failed_attempts)->toBe(0);

    // Another restaurant's owner has no card to touch.
    $this->actingAs($other)->post(route('billing.card.auto-renew'))->assertNotFound();
    $this->actingAs($other)->delete(route('billing.card.remove'))->assertRedirect();
    expect(SavedCard::withoutGlobalScopes()->where('restaurant_id', $r->id)->exists())->toBeTrue();

    $this->actingAs($u)->delete(route('billing.card.remove'))->assertRedirect();
    expect(SavedCard::withoutGlobalScopes()->where('restaurant_id', $r->id)->exists())->toBeFalse();
});
