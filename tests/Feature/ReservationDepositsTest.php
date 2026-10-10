<?php

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Orders\Services\RestaurantGateways;
use App\Modules\Reservations\Models\Reservation;
use App\Modules\Reservations\Services\ReservationService;
use App\Modules\Reservations\Services\ReservationSettings;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Mail::fake();
    Http::swap(new Factory);
    $this->withoutMiddleware(ThrottleRequests::class); // numeric throttles share one counter per IP
});

function rdShop(array $cfg = [], bool $stripe = true): array
{
    $r = Restaurant::create(['name' => 'Deposit Diner', 'slug' => 'rd'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => ['reservations' => true, 'online_payments' => true]]), now()->addMonth());
    app(ReservationSettings::class)->save($r, array_replace(ReservationSettings::DEFAULTS, ['enabled' => true, 'auto_confirm' => true, 'open_from' => '12:00', 'open_to' => '16:00', 'duration_minutes' => 60, 'slot_minutes' => 30, 'lead_minutes' => 0, 'max_covers' => 4, 'deposit_per_person' => 10, 'deposit_hold_minutes' => 15, 'deposit_refund_hours' => 24], $cfg));

    if ($stripe) {
        app(RestaurantGateways::class)->save($r, 'stripe', true, ['secret_key' => 'sk_test_123', 'webhook_secret' => 'whsec_test']);
    }

    return [$r];
}

function rdBook($test, Restaurant $r, array $over = [])
{
    return $test->post('/r/'.$r->slug.'/reserve', $over + ['name' => 'Gus', 'email' => 'gus@example.com', 'party_size' => 2, 'date' => now()->addDays(3)->format('Y-m-d'), 'time' => '13:00']);
}

function rdRes(Restaurant $r): Reservation
{
    return app(TenantContext::class)->runAs($r, fn () => Reservation::latest('id')->first());
}

function rdPaid($test, Restaurant $r, string $reference, int $amount = 2000, string $txn = 'pi_dep')
{
    $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'paid', 'client_reference_id' => $reference, 'payment_intent' => $txn, 'amount_total' => $amount, 'currency' => 'usd']]]);
    $t = time();

    return $test->call('POST', '/r/'.$r->slug.'/pay/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', $t.'.'.$payload, 'whsec_test')], $payload);
}

function rdFakeCheckout(): void
{
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_dep', 'url' => 'https://checkout.stripe.test/dep']), 'api.stripe.com/v1/refunds' => Http::response(['id' => 're_1'])]);
}

it('asks for no deposit unless one is set and a gateway is ready', function () {
    [$none] = rdShop(['deposit_per_person' => 0]);
    [$nogateway] = rdShop([], stripe: false);
    foreach ([$none, $nogateway] as $r) {
        rdBook($this, $r)->assertRedirect();
        expect(rdRes($r))->status->toBe('confirmed')->deposit_cents->toBe(0)->deposit_status->toBe('none');
    }
});

it('holds the table and sends the guest to the payment page', function () {
    rdFakeCheckout();
    [$r] = rdShop();
    rdBook($this, $r)->assertRedirect('https://checkout.stripe.test/dep');

    $res = rdRes($r);
    expect($res)->status->toBe('awaiting')->deposit_cents->toBe(2000)->deposit_status->toBe('pending')->deposit_reference->toBe('D'.$r->id.'R'.$res->id);
    Http::assertSent(fn ($q) => str_contains($q->url(), 'checkout/sessions') && $q['line_items'][0]['price_data']['unit_amount'] === 2000 && $q['client_reference_id'] === $res->deposit_reference);
    Mail::assertNothingSent(); // nothing is confirmed until the deposit is paid

    // The held table counts: with 4 seats and 2 held, another party of 4 no longer fits at 13:00.
    $slots = $this->getJson('/r/'.$r->slug.'/reserve/slots?date='.now()->addDays(3)->format('Y-m-d').'&party=4')->json('slots');
    expect($slots)->not->toContain('13:00');
});

it('confirms the booking when the verified payment arrives, once, for the right amount only', function () {
    rdFakeCheckout();
    [$r] = rdShop();
    rdBook($this, $r);
    $res = rdRes($r);

    rdPaid($this, $r, $res->deposit_reference, 1999)->assertOk(); // wrong amount: ignored
    expect(rdRes($r))->status->toBe('awaiting')->deposit_status->toBe('pending');

    rdPaid($this, $r, $res->deposit_reference)->assertOk();
    rdPaid($this, $r, $res->deposit_reference)->assertOk(); // a replayed webhook changes nothing
    expect(rdRes($r))->status->toBe('confirmed')->deposit_status->toBe('paid')->deposit_transaction->toBe('pi_dep');
    Mail::assertSent(TemplatedMail::class, 1);
});

it('sends paid deposits to staff first when bookings need manual confirmation', function () {
    rdFakeCheckout();
    [$r] = rdShop(['auto_confirm' => false]);
    rdBook($this, $r);
    rdPaid($this, $r, rdRes($r)->deposit_reference)->assertOk();
    expect(rdRes($r))->status->toBe('pending')->deposit_status->toBe('paid');
});

it('releases unpaid bookings after the hold, and pays back a deposit that arrives too late', function () {
    rdFakeCheckout();
    [$r] = rdShop();
    rdBook($this, $r);
    $res = rdRes($r);

    $this->travel(10)->minutes();
    $this->artisan('reservations:expire')->assertSuccessful();
    expect(rdRes($r)->status)->toBe('awaiting');

    $this->travel(10)->minutes();
    $this->artisan('reservations:expire')->assertSuccessful();
    expect(rdRes($r))->status->toBe('cancelled')->deposit_status->toBe('failed');

    // The guest paid in the meantime: the table is gone, so the money goes back.
    app(TenantContext::class)->runAs($r, fn () => Reservation::whereKey($res->id)->update(['deposit_status' => 'pending']));
    rdPaid($this, $r, $res->deposit_reference)->assertOk();
    expect(rdRes($r)->deposit_status)->toBe('refunded');
    Http::assertSent(fn ($q) => str_contains($q->url(), 'v1/refunds') && $q['payment_intent'] === 'pi_dep');
});

it('pays back early cancellations and staff cancellations, keeps late ones and no-shows, and credits visits', function () {
    rdFakeCheckout();
    [$r] = rdShop();
    $svc = app(ReservationService::class);
    $make = function (string $date) use ($r) {
        rdBook($this, $r, ['date' => $date]);
        $res = rdRes($r);
        rdPaid($this, $r, $res->deposit_reference, 2000, 'pi_'.$res->id)->assertOk();

        return rdRes($r);
    };
    $act = fn ($res, string $to, string $by = 'staff') => app(TenantContext::class)->runAs($r, fn () => $svc->setStatus($r, Reservation::find($res->id), $to, $by));

    $early = $make(now()->addDays(5)->format('Y-m-d'));
    $act($early, 'cancelled', 'guest');
    $late = $make(now()->addHours(30)->format('Y-m-d'));
    app(TenantContext::class)->runAs($r, fn () => Reservation::whereKey($late->id)->update(['starts_at' => now()->addHours(5)]));
    $act($late, 'cancelled', 'guest');
    $staff = $make(now()->addDays(4)->format('Y-m-d'));
    $act($staff, 'cancelled', 'staff');
    $noshow = $make(now()->addDays(6)->format('Y-m-d'));
    $act($noshow, 'no_show');
    $visit = $make(now()->addDays(7)->format('Y-m-d'));
    $act($visit, 'seated');

    $state = fn ($res) => app(TenantContext::class)->runAs($r, fn () => Reservation::find($res->id)->deposit_status);
    expect($state($early))->toBe('refunded')->and($state($late))->toBe('forfeited')->and($state($staff))->toBe('refunded')->and($state($noshow))->toBe('forfeited')->and($state($visit))->toBe('applied');
});

it('flags a deposit for a manual refund when the gateway cannot pay it back, then lets staff close it', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_dep', 'url' => 'https://checkout.stripe.test/dep']), 'api.stripe.com/v1/refunds' => Http::response(['error' => ['message' => 'nope']], 400)]);
    [$r] = rdShop();
    rdBook($this, $r);
    $res = rdRes($r);
    rdPaid($this, $r, $res->deposit_reference)->assertOk();
    app(TenantContext::class)->runAs($r, fn () => app(ReservationService::class)->setStatus($r, Reservation::find($res->id), 'cancelled', 'staff'));
    expect(rdRes($r)->deposit_status)->toBe('refund_due');

    $owner = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $owner->assignRole('restaurant_owner');
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->actingAs($owner)->get(route('reservations.index', ['date' => now()->addDays(3)->format('Y-m-d')]))->assertOk()->assertSee('to pay back')->assertSee('Mark as paid back');
    $this->actingAs($owner)->post(route('reservations.deposit.refunded', $res->id))->assertRedirect();
    expect(rdRes($r)->deposit_status)->toBe('refunded');
});

it('never asks staff-made bookings for a deposit, and releases the table if the payment page cannot open', function () {
    [$r] = rdShop();
    $made = app(TenantContext::class)->runAs($r, fn () => app(ReservationService::class)->book($r, ['name' => 'Walk-in', 'phone' => '+1 555 000 1111', 'party_size' => 2, 'date' => now()->addDays(3)->format('Y-m-d'), 'time' => '13:00'], 'staff'));
    expect($made)->status->toBe('confirmed')->deposit_cents->toBe(0);

    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['error' => ['message' => 'down']], 500)]);
    rdBook($this, $r, ['time' => '14:00'])->assertRedirect('/r/'.$r->slug.'/reserve')->assertSessionHasErrors('time');
    expect(rdRes($r))->status->toBe('cancelled');
});

it('lets a guest finish paying later from the booking link, and shows the deposit state', function () {
    rdFakeCheckout();
    [$r] = rdShop();
    rdBook($this, $r);
    $res = rdRes($r);
    $this->get('/r/'.$r->slug.'/reserve/'.$res->token)->assertOk()->assertSee('Pay the deposit')->assertSee('$20.00');
    $this->post('/r/'.$r->slug.'/reserve/'.$res->token.'/pay')->assertRedirect('https://checkout.stripe.test/dep');
    rdPaid($this, $r, $res->deposit_reference)->assertOk();
    $this->get('/r/'.$r->slug.'/reserve/'.$res->token)->assertOk()->assertDontSee('Pay the deposit')->assertSee('paid');
    $this->post('/r/'.$r->slug.'/reserve/'.$res->token.'/pay')->assertNotFound(); // nothing left to pay
});

it('validates the deposit settings', function () {
    [$r] = rdShop();
    $owner = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $owner->assignRole('restaurant_owner');
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $base = ['enabled' => 1, 'open_from' => '10:00', 'open_to' => '23:00', 'slot_minutes' => 30, 'duration_minutes' => 90, 'max_party' => 10, 'lead_minutes' => 30, 'days_ahead' => 30, 'max_covers' => 40, 'remind_hours' => 2];
    $this->actingAs($owner)->put(route('reservations.settings.update'), $base + ['deposit_per_person' => '7.50', 'deposit_hold_minutes' => 20, 'deposit_refund_hours' => 48])->assertSessionHasNoErrors();
    expect(app(ReservationSettings::class)->depositCents($r, 4))->toBe(3000);
    $this->actingAs($owner)->put(route('reservations.settings.update'), $base + ['deposit_per_person' => '-1'])->assertSessionHasErrors('deposit_per_person');
    $this->actingAs($owner)->put(route('reservations.settings.update'), $base + ['deposit_hold_minutes' => 1])->assertSessionHasErrors('deposit_hold_minutes');
});
