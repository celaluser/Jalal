<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Reservations\Models\Reservation;
use App\Modules\Reservations\Services\ReservationSettings;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Cache::flush();
});

function rsShop(array $cfg = [], bool $feature = true, string $role = Permissions::OWNER): array
{
    $r = Restaurant::create(['name' => 'Book Bistro', 'slug' => 'rs'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => ['reservations' => $feature]]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $u = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $u->assignRole($role);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));
    app(ReservationSettings::class)->save($r, array_replace(ReservationSettings::DEFAULTS, ['enabled' => true, 'open_from' => '12:00', 'open_to' => '16:00', 'duration_minutes' => 60, 'slot_minutes' => 30, 'lead_minutes' => 0, 'max_covers' => 8], $cfg));

    return [$r, $u];
}

function rsDay(int $daysAhead = 2): string
{
    return CarbonImmutable::now('UTC')->addDays($daysAhead)->toDateString();
}

function rsBook($test, Restaurant $r, array $over = [])
{
    return $test->post('/r/'.$r->slug.'/reserve', $over + ['name' => 'Gus', 'email' => 'gus@example.com', 'party_size' => 2, 'date' => rsDay(), 'time' => '13:00']);
}

it('lists free times inside opening hours and shuts out bad days', function () {
    [$r] = rsShop();
    $slots = $this->getJson('/r/'.$r->slug.'/reserve/slots?date='.rsDay().'&party=2')->assertOk()->json('slots');
    expect($slots)->toBe(['12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00']); // a 60 minute stay must end by 16:00
    expect($this->getJson('/r/'.$r->slug.'/reserve/slots?date='.now()->subDays(2)->toDateString().'&party=2')->json('slots'))->toBe([]);
    expect($this->getJson('/r/'.$r->slug.'/reserve/slots?date='.now()->addDays(90)->toDateString().'&party=2')->json('slots'))->toBe([]);
    expect($this->getJson('/r/'.$r->slug.'/reserve/slots?date='.rsDay().'&party=50')->json('slots'))->toBe([]);
    $this->getJson('/r/'.$r->slug.'/reserve/slots?date=nonsense&party=2')->assertStatus(422);
});

it('only offers the days the restaurant is open for bookings', function () {
    $dow = CarbonImmutable::parse(rsDay())->dayOfWeekIso;
    [$r] = rsShop(['days' => [$dow]]);
    expect($this->getJson('/r/'.$r->slug.'/reserve/slots?date='.rsDay().'&party=2')->json('slots'))->not->toBe([]);
    expect($this->getJson('/r/'.$r->slug.'/reserve/slots?date='.rsDay(3).'&party=2')->json('slots'))->toBe([]);
});

it('books a table, tells the guest, and lets them cancel from their link', function () {
    Mail::fake();
    [$r] = rsShop();
    $res = rsBook($this, $r)->assertRedirect();
    $b = app(TenantContext::class)->runAs($r, fn () => Reservation::first());
    expect($b->status)->toBe('pending')->and($b->starts_at->format('H:i'))->toBe('13:00')->and($b->token)->toHaveLength(24);
    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'reservation_received' && $m->hasTo('gus@example.com'));

    $this->get('/r/'.$r->slug.'/reserve/'.$b->token)->assertOk()->assertSee('13:00')->assertSee(__('reservations.status_pending'));
    $this->post('/r/'.$r->slug.'/reserve/'.$b->token.'/cancel')->assertRedirect();
    expect(app(TenantContext::class)->runAs($r, fn () => Reservation::first()->status))->toBe('cancelled');
    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'reservation_cancelled');
    $this->post('/r/'.$r->slug.'/reserve/'.$b->token.'/cancel')->assertSessionHasErrors('reservation');
    $this->get('/r/'.$r->slug.'/reserve/'.str_repeat('z', 24))->assertNotFound();
});

it('refuses times that are taken, past, or not offered, and bookings with no way to reach the guest', function () {
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // this test books more often than the real limit allows
    [$r] = rsShop(['max_covers' => 4]);
    rsBook($this, $r, ['party_size' => 4])->assertRedirect();
    rsBook($this, $r, ['party_size' => 2])->assertSessionHasErrors('time');          // 13:00 is full
    rsBook($this, $r, ['party_size' => 2, 'time' => '14:00'])->assertRedirect();      // the first stay ended at 14:00
    rsBook($this, $r, ['time' => '09:00'])->assertSessionHasErrors('time');           // outside hours
    rsBook($this, $r, ['time' => '13:10'])->assertSessionHasErrors('time');           // not a slot
    rsBook($this, $r, ['date' => now()->subDay()->toDateString()])->assertSessionHasErrors('time');
    rsBook($this, $r, ['email' => null, 'phone' => null, 'time' => '15:00'])->assertSessionHasErrors('time');
    rsBook($this, $r, ['phone' => 'abc', 'time' => '15:00'])->assertSessionHasErrors('phone');
    expect(app(TenantContext::class)->runAs($r, fn () => Reservation::count()))->toBe(2);
});

it('uses tables when there are some: a table for each booking, sized to the party', function () {
    [$r] = rsShop();
    app(TenantContext::class)->runAs($r, function () {
        DiningTable::create(['name' => 'A', 'seats' => 2]);
        DiningTable::create(['name' => 'B', 'seats' => 6]);
    });
    $slots = fn (int $party) => $this->getJson('/r/'.$r->slug.'/reserve/slots?date='.rsDay().'&party='.$party)->json('slots');
    expect($slots(6))->toContain('13:00');
    rsBook($this, $r, ['party_size' => 6])->assertRedirect();
    expect($slots(6))->not->toContain('13:00')->and($slots(2))->toContain('13:00');
    rsBook($this, $r, ['party_size' => 2])->assertRedirect();
    expect($slots(2))->not->toContain('13:00')->and($slots(2))->toContain('14:00'); // both tables busy at 13:00
});

it('is invisible when switched off or when the plan lacks reservations', function () {
    [$off] = rsShop(['enabled' => false]);
    [$noPlan] = rsShop([], false);
    foreach ([$off, $noPlan] as $r) {
        $this->get('/r/'.$r->slug.'/reserve')->assertNotFound();
        $this->getJson('/r/'.$r->slug.'/reserve/slots?date='.rsDay().'&party=2')->assertNotFound();
        $this->get('/r/'.$r->slug)->assertOk()->assertDontSee('/reserve');
    }
    [$on] = rsShop();
    $this->get('/r/'.$on->slug)->assertOk()->assertSee(__('reservations.reserve_link'));
    $this->get('/r/'.$on->slug.'/reserve')->assertOk()->assertSee(__('reservations.book_title'));
});

it('lets staff confirm, seat and finish a booking, and picks a fitting table', function () {
    Mail::fake();
    [$r, $owner] = rsShop();
    app(TenantContext::class)->runAs($r, function () {
        DiningTable::create(['name' => 'Big', 'seats' => 8]);
        DiningTable::create(['name' => 'Small', 'seats' => 2]);
    });
    rsBook($this, $r, ['party_size' => 2]);
    $id = app(TenantContext::class)->runAs($r, fn () => Reservation::first()->id);

    $this->actingAs($owner)->get(route('reservations.index', ['date' => rsDay()]))->assertOk()->assertSee('Gus')->assertSee(__('reservations.action_confirmed'));
    $this->actingAs($owner)->post(route('reservations.status', $id), ['status' => 'confirmed'])->assertRedirect();
    $b = app(TenantContext::class)->runAs($r, fn () => Reservation::with('table')->first());
    expect($b->status)->toBe('confirmed')->and($b->table->name)->toBe('Small'); // the smallest table that fits
    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'reservation_confirmed');

    $this->actingAs($owner)->post(route('reservations.status', $id), ['status' => 'completed'])->assertSessionHasErrors('reservation'); // must be seated first
    foreach (['seated', 'completed'] as $to) {
        $this->actingAs($owner)->post(route('reservations.status', $id), ['status' => $to])->assertRedirect();
    }
    expect(app(TenantContext::class)->runAs($r, fn () => Reservation::first()->status))->toBe('completed');
});

it('takes a booking by phone, even into a full slot when told to', function () {
    [$r, $owner] = rsShop(['max_covers' => 2]);
    $body = ['name' => 'Walk-in', 'phone' => '+1 555 000 1111', 'party_size' => 2, 'date' => rsDay(), 'time' => '13:00'];
    $this->actingAs($owner)->post(route('reservations.store'), $body)->assertRedirect();
    $b = app(TenantContext::class)->runAs($r, fn () => Reservation::first());
    expect($b->status)->toBe('confirmed')->and($b->source)->toBe('staff'); // staff bookings need no confirmation
    $this->actingAs($owner)->post(route('reservations.store'), ['name' => 'Second'] + $body)->assertSessionHasErrors('time');
    $this->actingAs($owner)->post(route('reservations.store'), ['name' => 'Second', 'force' => 1] + $body)->assertRedirect();
    expect(app(TenantContext::class)->runAs($r, fn () => Reservation::count()))->toBe(2);
});

it('keeps reservations to staff who may see them and to their own restaurant', function () {
    [$r, $owner] = rsShop();
    rsBook($this, $r);
    $id = app(TenantContext::class)->runAs($r, fn () => Reservation::first()->id);
    $this->actingAs(opsUserFor($r, Permissions::KITCHEN))->get(route('reservations.index'))->assertForbidden();
    $this->actingAs(opsUserFor($r, Permissions::WAITER))->get(route('reservations.index'))->assertOk();
    $this->actingAs(opsUserFor($r, Permissions::WAITER))->get(route('reservations.settings'))->assertForbidden();
    [, $other] = rsShop();
    $this->actingAs($other)->post(route('reservations.status', $id), ['status' => 'confirmed'])->assertNotFound();
    $this->actingAs($other)->get(route('reservations.index', ['date' => rsDay()]))->assertDontSee('Gus');
});

function opsUserFor(Restaurant $r, string $role): User
{
    $u = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $u->assignRole($role);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $u;
}

it('saves the settings and refuses nonsense', function () {
    [$r, $owner] = rsShop();
    $body = ['enabled' => 1, 'open_from' => '10:00', 'open_to' => '23:00', 'slot_minutes' => 15, 'duration_minutes' => 120, 'max_party' => 12, 'lead_minutes' => 30, 'days_ahead' => 60, 'max_covers' => 50, 'remind_hours' => 3, 'days' => [5, 6]];
    $this->actingAs($owner)->put(route('reservations.settings.update'), $body)->assertRedirect();
    $s = app(ReservationSettings::class)->for($r);
    expect([$s['open_from'], $s['slot_minutes'], $s['days'], $s['auto_confirm']])->toBe(['10:00', 15, [5, 6], false]);
    $this->actingAs($owner)->put(route('reservations.settings.update'), ['open_to' => '09:00'] + $body)->assertSessionHasErrors('open_to');
    $this->actingAs($owner)->put(route('reservations.settings.update'), ['slot_minutes' => 7] + $body)->assertSessionHasErrors('slot_minutes');
    $this->actingAs($owner)->get(route('reservations.settings'))->assertOk();
});

it('reminds guests once, only for confirmed bookings inside the window', function () {
    Mail::fake();
    [$r, $owner] = rsShop(['remind_hours' => 3, 'auto_confirm' => true, 'lead_minutes' => 0]);
    $mk = fn (string $when, string $email, string $status = 'confirmed') => app(TenantContext::class)->runAs($r, fn () => (new Reservation(['name' => 'X', 'email' => $email, 'party_size' => 2, 'starts_at' => $when, 'duration_minutes' => 60, 'status' => $status, 'source' => 'staff']))->forceFill(['token' => Str::lower(Str::random(24))])->save());
    $mk(now()->addHour()->toDateTimeString(), 'soon@example.com');
    $mk(now()->addHours(6)->toDateTimeString(), 'later@example.com');
    $mk(now()->addHour()->toDateTimeString(), 'pending@example.com', 'pending');
    $this->artisan('reservations:remind')->assertSuccessful();
    $this->artisan('reservations:remind')->assertSuccessful();
    $sent = Mail::sent(TemplatedMail::class)->filter(fn ($m) => $m->templateKey === 'reservation_reminder');
    expect($sent)->toHaveCount(1)->and($sent->first()->hasTo('soon@example.com'))->toBeTrue();
});

it('also texts the reminder to guests with only a phone number, in their language, once', function () {
    Mail::fake();
    $m = app(\App\Modules\Messaging\Services\MessagingManager::class);
    $m->save($m->find('twilio'), ['account_sid' => 'AC1', 'auth_token' => 'tok', 'from' => '+15550001111']);
    $m->choose('sms', 'twilio');
    \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory);
    \Illuminate\Support\Facades\Http::fake(['api.twilio.com/*' => \Illuminate\Support\Facades\Http::response(['sid' => 'SM1'], 201)]);

    [$r] = rsShop(['remind_hours' => 3, 'auto_confirm' => true, 'lead_minutes' => 0]);
    app(TenantContext::class)->runAs($r, fn () => (new Reservation(['name' => 'Tuna', 'phone' => '+90 532 111 22 33', 'party_size' => 4, 'starts_at' => now()->addHour()->toDateTimeString(), 'duration_minutes' => 90, 'status' => 'confirmed', 'locale' => 'tr']))->forceFill(['token' => Str::lower(Str::random(24))])->save());
    $this->artisan('reservations:remind')->assertSuccessful();
    $this->artisan('reservations:remind')->assertSuccessful();

    \Illuminate\Support\Facades\Http::assertSentCount(1);
    \Illuminate\Support\Facades\Http::assertSent(fn ($q) => $q['To'] === '+905321112233' && str_contains($q['Body'], 'hatırlatma') && str_contains($q['Body'], '4 kişilik'));
    Mail::assertNothingSent();
});
