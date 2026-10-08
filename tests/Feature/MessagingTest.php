<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Messaging\Models\MessageLog;
use App\Modules\Messaging\Services\MessagingManager;
use App\Modules\Messaging\Services\Messenger;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
});

function msSetup(string $channel, string $code, array $values): void
{
    $m = app(MessagingManager::class);
    $m->save($m->find($code), $values);
    $m->choose($channel, $code);
}

function msShop(?int $allowance = null, string $callingCode = ''): Restaurant
{
    $r = Restaurant::create(['name' => 'Msg', 'slug' => 'ms'.uniqid(), 'locale' => 'en', 'currency_code' => 'USD', 'marketing_settings' => ['calling_code' => $callingCode]]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => ['messages' => $allowance], 'features' => []]), now()->addMonth());

    return $r;
}

it('sends SMS through Twilio with the right sender and number', function () {
    msSetup('sms', 'twilio', ['account_sid' => 'AC1', 'auth_token' => 'tok', 'from' => '+15550001111']);
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

    expect(app(Messenger::class)->send(msShop(), 'sms', '+90 532 111 22 33', 'Hello', 'test'))->toBeTrue();
    Http::assertSent(fn ($q) => str_contains($q->url(), 'Accounts/AC1/Messages.json') && $q['To'] === '+905321112233' && $q['From'] === '+15550001111' && $q['Body'] === 'Hello');
    expect(MessageLog::first())->status->toBe('sent')->recipient->toEndWith('2233')->and(MessageLog::first()->recipient)->not->toContain('532');
});

it('sends WhatsApp through Twilio with the whatsapp prefix and a messaging service', function () {
    msSetup('whatsapp', 'twilio', ['account_sid' => 'AC1', 'auth_token' => 't', 'whatsapp_from' => 'MGabc']);
    Http::fake(['api.twilio.com/*' => Http::response([], 201)]);
    app(Messenger::class)->send(null, 'whatsapp', '+905321112233', 'Hi');
    Http::assertSent(fn ($q) => $q['To'] === 'whatsapp:+905321112233' && $q['MessagingServiceSid'] === 'MGabc');
});

it('sends through Vonage, MessageBird and the WhatsApp Cloud API', function () {
    Http::fake([
        'rest.nexmo.com/*' => Http::response(['messages' => [['status' => '0']]]),
        'rest.messagebird.com/*' => Http::response(['id' => 'x'], 201),
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid']]]),
    ]);
    $m = app(Messenger::class);

    msSetup('sms', 'vonage', ['api_key' => 'k', 'api_secret' => 's', 'from' => 'Menu']);
    expect($m->send(null, 'sms', '+441234567890', 'Hi'))->toBeTrue();
    msSetup('sms', 'messagebird', ['access_key' => 'ak', 'originator' => 'Menu']);
    expect($m->send(null, 'sms', '+441234567890', 'Hi'))->toBeTrue();
    msSetup('whatsapp', 'whatsapp_cloud', ['phone_number_id' => '123', 'access_token' => 'tok', 'template' => 'order_update', 'template_language' => 'tr']);
    expect($m->send(null, 'whatsapp', '+441234567890', 'Your order is ready'))->toBeTrue();

    Http::assertSent(fn ($q) => str_contains($q->url(), 'graph.facebook.com/v20.0/123/messages') && $q['type'] === 'template' && $q['template']['language']['code'] === 'tr' && $q['template']['components'][0]['parameters'][0]['text'] === 'Your order is ready');
    Http::assertSent(fn ($q) => str_contains($q->url(), 'messagebird') && $q->hasHeader('Authorization', 'AccessKey ak'));
});

it('treats a Vonage rejection inside a 200 response as a failure, and logs it without leaking keys', function () {
    msSetup('sms', 'vonage', ['api_key' => 'k', 'api_secret' => 'topsecret', 'from' => 'Menu']);
    Http::fake(['rest.nexmo.com/*' => Http::response(['messages' => [['status' => '4', 'error-text' => 'invalid credentials']]])]);

    expect(app(Messenger::class)->send(null, 'sms', '+441234567890', 'Hi'))->toBeFalse();
    $log = MessageLog::first();
    expect($log->status)->toBe('failed')->and($log->error)->toContain('invalid credentials')->and($log->error)->not->toContain('topsecret');
});

it('does nothing and never throws without a configured provider, or with a bad number', function () {
    $m = app(Messenger::class);
    Http::fake(['api.twilio.com/*' => Http::response(['message' => 'boom'], 500)]);
    expect($m->send(null, 'sms', '+441234567890', 'Hi'))->toBeFalse();
    Http::assertNothingSent();

    msSetup('sms', 'twilio', ['account_sid' => 'AC1', 'auth_token' => 't', 'from' => '+15550001111']);
    expect($m->send(null, 'sms', '0532 111 22 33', 'Hi'))->toBeFalse()->and($m->send(null, 'sms', 'call me', 'Hi'))->toBeFalse()->and($m->send(null, 'sms', '+12', 'Hi'))->toBeFalse();
    Http::assertNothingSent();

    expect($m->send(null, 'sms', '+441234567890', 'Hi'))->toBeFalse(); // the provider answers 500
});

it('uses the restaurant’s calling code for local numbers', function () {
    $m = app(Messenger::class);
    expect($m->normalize('0532 111 22 33', msShop(callingCode: '90')))->toBe('+905321112233')->and($m->normalize('0090 532 111 22 33'))->toBe('+905321112233')->and($m->normalize('0532 111 22 33', msShop()))->toBeNull();
});

it('stops at the plan’s monthly allowance', function () {
    msSetup('sms', 'twilio', ['account_sid' => 'AC1', 'auth_token' => 't', 'from' => '+15550001111']);
    Http::fake(['api.twilio.com/*' => Http::response([], 201)]);
    $r = msShop(2);
    $m = app(Messenger::class);

    expect($m->send($r, 'sms', '+441234567890', 'a'))->toBeTrue()->and($m->send($r, 'sms', '+441234567890', 'b'))->toBeTrue()->and($m->send($r, 'sms', '+441234567890', 'c'))->toBeFalse();
    expect($m->usage($r))->toBe(['used' => 2, 'limit' => 2])->and(MessageLog::where('status', 'skipped')->count())->toBe(1);
});

describe('admin screen', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
        $this->admin->assignRole(Permissions::SUPER_ADMIN);
    });

    it('saves keys encrypted, keeps a secret when left blank, and chooses providers per channel', function () {
        $this->actingAs($this->admin)->get(route('admin.messaging.edit'))->assertOk()->assertSee('Twilio')->assertSee('Vonage');
        $this->actingAs($this->admin)->put(route('admin.messaging.update', 'vonage'), ['api_key' => 'k1', 'api_secret' => 's1', 'from' => 'Menu'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->put(route('admin.messaging.update', 'vonage'), ['api_key' => 'k2', 'api_secret' => '', 'from' => 'Menu']);

        $s = app(SettingsService::class);
        expect($s->get('messaging.vonage.api_key'))->toBe('k2')->and($s->get('messaging.vonage.api_secret'))->toBe('s1');
        expect(Setting::where('key', 'messaging.vonage.api_secret')->value('value'))->not->toBe('s1');

        $this->actingAs($this->admin)->put(route('admin.messaging.choose'), ['sms_provider' => 'vonage', 'whatsapp_provider' => ''])->assertSessionHasNoErrors();
        expect(app(MessagingManager::class)->available('sms'))->toBeTrue()->and(app(MessagingManager::class)->available('whatsapp'))->toBeFalse();
        $this->actingAs($this->admin)->put(route('admin.messaging.choose'), ['sms_provider' => 'whatsapp_cloud'])->assertStatus(422); // wrong channel
    });

    it('sends a test message', function () {
        msSetup('sms', 'messagebird', ['access_key' => 'ak', 'originator' => 'Menu']);
        Http::fake(['rest.messagebird.com/*' => Http::response([], 201)]);
        $this->actingAs($this->admin)->post(route('admin.messaging.test'), ['channel' => 'sms', 'to' => '+441234567890'])->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->actingAs($this->admin)->post(route('admin.messaging.test'), ['channel' => 'whatsapp', 'to' => '+441234567890'])->assertSessionHasErrors('to');
    });

    it('is closed to everyone else', function () {
        $this->actingAs(User::factory()->create())->get(route('admin.messaging.edit'))->assertForbidden();
    });
});
