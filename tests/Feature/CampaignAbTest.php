<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Mail\CampaignMail;
use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Models\CampaignRecipient;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Services\CampaignService;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Mail::fake();
});

function abShop(int $customers = 20): array
{
    $r = Restaurant::create(['name' => 'Mail Place', 'slug' => 'ab'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'onboarded_at' => now(), 'marketing_settings' => ['campaign_daily_cap' => 500]]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => []]), now()->addMonth());
    $u = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $u->assignRole(Permissions::MANAGER);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));
    app(TenantContext::class)->runAs($r, function () use ($customers) {
        foreach (range(1, $customers) as $i) {
            Customer::create(['name' => 'Cu'.$i, 'email' => "cu{$i}-".uniqid().'@example.com', 'marketing_opt_in' => true, 'opted_in_at' => now(), 'orders_count' => 1]);
        }
    });

    return [$r, $u];
}

function abIn(Restaurant $r, Closure $fn): mixed
{
    return app(TenantContext::class)->runAs($r, $fn);
}

function abCampaign(Restaurant $r, array $over = []): Campaign
{
    return abIn($r, fn () => Campaign::create($over + ['name' => 'Spring', 'subject' => 'Subject A', 'subject_b' => 'Subject B', 'ab_test' => true, 'test_percent' => 40, 'winner_hours' => 2, 'body' => 'Hello {{name}}']));
}

it('sends a test group first and holds the rest back', function () {
    [$r, $u] = abShop(20);
    $c = abCampaign($r);

    $this->actingAs($u)->post(route('campaigns.send', $c->id))->assertRedirect(route('campaigns.show', $c->id));

    $fresh = abIn($r, fn () => $c->fresh());
    expect($fresh->status)->toBe('testing')->and($fresh->test_sent_at)->not->toBeNull()->and($fresh->winner)->toBeNull();
    $rows = abIn($r, fn () => CampaignRecipient::where('campaign_id', $c->id)->get());
    // 40% of 20 = 8 people: 4 on each subject; 12 wait.
    expect($rows->where('variant', 'A')->where('status', 'sent'))->toHaveCount(4)->and($rows->where('variant', 'B')->where('status', 'sent'))->toHaveCount(4)->and($rows->where('status', 'held'))->toHaveCount(12);
    Mail::assertSent(CampaignMail::class, 8);
    Mail::assertSent(CampaignMail::class, fn (CampaignMail $m) => $m->hasSubject('Subject A'));
    Mail::assertSent(CampaignMail::class, fn (CampaignMail $m) => $m->hasSubject('Subject B'));
});

it('counts opens through the signed picture, once per person but with every view', function () {
    [$r, $u] = abShop(20);
    $c = abCampaign($r);
    $this->actingAs($u)->post(route('campaigns.send', $c->id));
    $row = abIn($r, fn () => CampaignRecipient::where('campaign_id', $c->id)->where('status', 'sent')->first());

    $url = URL::signedRoute('marketing.open', ['recipient' => $row->id]);
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/gif');
    $this->get($url)->assertOk();
    $fresh = $row->fresh();
    expect($fresh->opened_at)->not->toBeNull()->and($fresh->open_count)->toBe(2);

    // A guessed or altered address does nothing.
    $this->get(route('marketing.open', ['recipient' => $row->id]))->assertForbidden();
    $this->get(str_replace((string) $row->id, '999999', $url))->assertForbidden();

    // Every message carries the picture, and a campaign can switch tracking off.
    Mail::assertSent(CampaignMail::class, fn (CampaignMail $m) => str_contains($m->render(), '/m/o/'));
    Mail::fake();
    $quiet = abCampaign($r, ['name' => 'Quiet', 'ab_test' => false, 'track_opens' => false]);
    $this->actingAs($u)->post(route('campaigns.send', $quiet->id));
    Mail::assertSent(CampaignMail::class, fn (CampaignMail $m) => ! str_contains($m->render(), '/m/o/'));
});

it('picks the subject with more opens and sends it to everyone who was waiting', function () {
    [$r, $u] = abShop(20);
    $c = abCampaign($r);
    $this->actingAs($u)->post(route('campaigns.send', $c->id));

    // B gets three opens, A one.
    abIn($r, function () use ($c) {
        CampaignRecipient::where('campaign_id', $c->id)->where('variant', 'B')->limit(3)->get()->each->update(['opened_at' => now(), 'open_count' => 1]);
        CampaignRecipient::where('campaign_id', $c->id)->where('variant', 'A')->limit(1)->get()->each->update(['opened_at' => now(), 'open_count' => 1]);
    });
    expect(abIn($r, fn () => app(CampaignService::class)->variantStats($c->fresh())))->toBe(['A' => ['sent' => 4, 'opened' => 1, 'rate' => 25.0], 'B' => ['sent' => 4, 'opened' => 3, 'rate' => 75.0]]);

    // Not yet: the waiting time (2 hours) is not over.
    $this->artisan('marketing:pick-winners')->assertSuccessful();
    expect(abIn($r, fn () => $c->fresh()->status))->toBe('testing');

    Mail::fake();
    $this->travel(3)->hours();
    $this->artisan('marketing:pick-winners')->assertSuccessful();

    $fresh = abIn($r, fn () => $c->fresh());
    expect($fresh->winner)->toBe('B')->and($fresh->status)->toBe('sent')->and($fresh->sent_count)->toBe(20);
    Mail::assertSent(CampaignMail::class, 12);
    Mail::assertSent(CampaignMail::class, fn (CampaignMail $m) => $m->hasSubject('Subject B'));
    Mail::assertNotSent(CampaignMail::class, fn (CampaignMail $m) => $m->hasSubject('Subject A'));

    // Running it again changes nothing.
    Mail::fake();
    $this->artisan('marketing:pick-winners')->assertSuccessful();
    Mail::assertNothingSent();
});

it('lets the owner pick the winner by hand, once', function () {
    [$r, $u] = abShop(20);
    $c = abCampaign($r);
    $this->actingAs($u)->post(route('campaigns.send', $c->id));
    $this->actingAs($u)->get(route('campaigns.show', $c->id))->assertOk()->assertSee(__('campaign_ab.testing_title'))->assertSee(__('campaign_ab.results_title'));

    Mail::fake();
    $this->actingAs($u)->post(route('campaigns.winner', $c->id), ['variant' => 'A'])->assertRedirect(route('campaigns.show', $c->id))->assertSessionHas('status');
    expect(abIn($r, fn () => $c->fresh()->winner))->toBe('A');
    Mail::assertSent(CampaignMail::class, 12);

    $this->actingAs($u)->post(route('campaigns.winner', $c->id), ['variant' => 'B'])->assertSessionHas('error');
    $this->actingAs($u)->post(route('campaigns.winner', $c->id), ['variant' => 'C'])->assertSessionHasErrors('variant');
    expect(abIn($r, fn () => $c->fresh()->winner))->toBe('A');
});

it('refuses a subject test for a tiny audience and validates the form', function () {
    [$r, $u] = abShop(6);
    $c = abCampaign($r);
    $this->actingAs($u)->post(route('campaigns.send', $c->id))->assertSessionHasErrors('campaign');
    expect(abIn($r, fn () => $c->fresh()->status))->toBe('draft');
    Mail::assertNothingSent();

    $base = ['name' => 'N', 'subject' => 'A', 'channel' => 'email', 'body' => 'Hi', 'ab_test' => 1];
    $this->actingAs($u)->post(route('campaigns.store'), $base)->assertSessionHasErrors('subject_b');
    $this->actingAs($u)->post(route('campaigns.store'), $base + ['subject_b' => 'B', 'test_percent' => 90])->assertSessionHasErrors('test_percent');
    $this->actingAs($u)->post(route('campaigns.store'), $base + ['subject_b' => 'B', 'test_percent' => 30, 'winner_hours' => 6, 'track_opens' => 0])->assertSessionHasNoErrors();
    $saved = abIn($r, fn () => Campaign::where('name', 'N')->first());
    expect($saved->ab_test)->toBeTrue()->and($saved->subject_b)->toBe('B')->and($saved->test_percent)->toBe(30)->and($saved->track_opens)->toBeFalse();

    // SMS has no subject: the test fields are dropped.
    $this->actingAs($u)->post(route('campaigns.store'), ['name' => 'S', 'channel' => 'sms', 'body' => 'Hi', 'ab_test' => 1, 'subject_b' => 'x'])->assertSessionHasNoErrors();
    expect(abIn($r, fn () => Campaign::where('name', 'S')->first()->ab_test))->toBeFalse();

    $this->actingAs($u)->get(route('campaigns.create'))->assertOk()->assertSee(__('campaign_ab.enable'));
});

it('sends a normal campaign as before, with opens counted', function () {
    [$r, $u] = abShop(5);
    $c = abCampaign($r, ['ab_test' => false, 'subject_b' => null]);
    $this->actingAs($u)->post(route('campaigns.send', $c->id));

    Mail::assertSent(CampaignMail::class, 5);
    expect(abIn($r, fn () => $c->fresh()->status))->toBe('sent');
    $this->actingAs($u)->get(route('campaigns.show', $c->id))->assertOk()->assertSee(__('campaign_ab.results_title'));
});
