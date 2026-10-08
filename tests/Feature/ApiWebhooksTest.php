<?php

use App\Models\User;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Api\Models\WebhookDelivery;
use App\Modules\Api\Models\WebhookEndpoint;
use App\Modules\Api\Services\ApiTokens;
use App\Modules\Api\Services\UrlGuard;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

/** @return array{0: Restaurant, 1: Product} */
function apShop(bool $api = true): array
{
    $r = Restaurant::create(['name' => 'Api Cafe', 'slug' => 'ap'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'onboarded_at' => now()]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => $api ? ['api' => true] : []]), now()->addMonth());
    $p = app(TenantContext::class)->runAs($r, fn () => Product::create(['category_id' => Category::create(['name' => ['en' => 'Food'], 'sort' => 1])->id, 'name' => ['en' => 'Pizza'], 'price' => 10, 'sort' => 1]));

    return [$r, $p];
}

function apToken(Restaurant $r, array $abilities = ['menu:read', 'menu:write', 'orders:read', 'orders:write']): string
{
    return app(TenantContext::class)->runAs($r, fn () => app(ApiTokens::class)->issue('test', $abilities)['plain']);
}

function apOrder(Restaurant $r, Product $p): Order
{
    return app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'Gus', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $p->id, 'qty' => 2]]]));
}

function apUser(Restaurant $r, string $role): User
{
    $u = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $u->assignRole($role);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $u;
}

describe('rest api', function () {
    it('rejects missing, wrong and expired tokens the same way', function () {
        [$r] = apShop();
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer qrm_nope'])->assertUnauthorized();
        $expired = app(TenantContext::class)->runAs($r, fn () => app(ApiTokens::class)->issue('old', ['menu:read'], 1));
        $expired['token']->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$expired['plain']])->assertUnauthorized();
    });

    it('stores only a hash and shows the restaurant behind the token', function () {
        [$r] = apShop();
        $plain = apToken($r);
        expect(ApiToken::withoutGlobalScopes()->first()->token_hash)->toBe(hash('sha256', $plain))->and(ApiToken::withoutGlobalScopes()->first()->getAttributes())->not->toContain($plain);
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$plain])->assertOk()->assertJsonPath('restaurant.name', 'Api Cafe')->assertJsonPath('abilities.0', 'menu:read');
        expect(ApiToken::withoutGlobalScopes()->first()->last_used_at)->not->toBeNull();
    });

    it('needs the plan feature and a live restaurant', function () {
        [$r] = apShop(false);
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.apToken($r)])->assertForbidden();
        [$s] = apShop();
        $t = apToken($s);
        $s->update(['status' => Restaurant::STATUS_SUSPENDED, 'suspended_at' => now()]);
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$t])->assertUnauthorized();
    });

    it('limits every token to its abilities', function () {
        [$r, $p] = apShop();
        $read = ['Authorization' => 'Bearer '.apToken($r, ['menu:read'])];
        $this->getJson('/api/v1/menu', $read)->assertOk()->assertJsonPath('products.0.name.en', 'Pizza');
        $this->getJson('/api/v1/orders', $read)->assertForbidden()->assertJsonPath('required', 'orders:read');
        $this->patchJson("/api/v1/products/{$p->id}", ['price' => 1], $read)->assertForbidden();
    });

    it('updates price and availability, validating input', function () {
        [$r, $p] = apShop();
        $h = ['Authorization' => 'Bearer '.apToken($r)];
        $this->patchJson("/api/v1/products/{$p->id}", ['price' => 12.5, 'is_available' => false], $h)->assertOk()->assertJsonPath('product.price', 12.5)->assertJsonPath('product.is_available', false);
        $this->patchJson("/api/v1/products/{$p->id}", ['price' => -1], $h)->assertUnprocessable();
        expect(app(TenantContext::class)->runAs($r, fn () => Product::find($p->id)->is_available))->toBeFalse();
    });

    it('lists, shows and moves orders, and never crosses restaurants', function () {
        [$r, $p] = apShop();
        [$other, $op] = apShop();
        $order = apOrder($r, $p);
        $foreign = apOrder($other, $op);
        $h = ['Authorization' => 'Bearer '.apToken($r)];

        $this->getJson('/api/v1/orders?status=new', $h)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $order->id)->assertJsonPath('data.0.items.0.qty', 2)->assertJsonPath('data.0.total_cents', 2000);
        $this->getJson("/api/v1/orders/{$order->id}", $h)->assertOk()->assertJsonPath('order.customer.name', 'Gus');
        $this->getJson("/api/v1/orders/{$foreign->id}", $h)->assertNotFound();
        $this->postJson("/api/v1/orders/{$foreign->id}/status", ['status' => 'accepted'], $h)->assertNotFound();

        $this->postJson("/api/v1/orders/{$order->id}/status", ['status' => 'accepted'], $h)->assertOk()->assertJsonPath('order.status', 'accepted');
        $this->postJson("/api/v1/orders/{$order->id}/status", ['status' => 'new'], $h)->assertUnprocessable()->assertJsonPath('code', 'invalid_transition');
        $this->postJson("/api/v1/orders/{$order->id}/status", ['status' => 'bogus'], $h)->assertUnprocessable();
    });
});

describe('webhooks', function () {
    function apHook(Restaurant $r, array $events = ['*'], string $url = 'https://8.8.8.8/hook'): WebhookEndpoint
    {
        return app(TenantContext::class)->runAs($r, function () use ($events, $url) {
            $e = new WebhookEndpoint(['url' => $url, 'events' => $events]);
            $e->secret = 'whsec_test';
            $e->save();

            return $e;
        });
    }

    it('posts a signed message when an order is placed and when it changes', function () {
        Http::swap(new Factory);
        Http::fake(['8.8.8.8/*' => Http::response('ok', 200)]);
        [$r, $p] = apShop();
        $hook = apHook($r);
        $order = apOrder($r, $p);

        Http::assertSent(function ($req) {
            $body = $req->body();
            preg_match('/t=(\d+),v1=([a-f0-9]{64})/', $req->header('X-Webhook-Signature')[0] ?? '', $m);

            return $req->url() === 'https://8.8.8.8/hook' && $req->header('X-Webhook-Event')[0] === 'order.created' && $m && hash_equals(hash_hmac('sha256', $m[1].'.'.$body, 'whsec_test'), $m[2])
                && json_decode($body, true)['data']['order']['items'][0]['qty'] === 2;
        });
        expect(WebhookDelivery::withoutGlobalScopes()->first())->status->toBe('sent')->response_code->toBe(200);

        app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->transition($order, 'accepted'));
        Http::assertSent(fn ($req) => ($req->header('X-Webhook-Event')[0] ?? '') === 'order.status_changed' && json_decode($req->body(), true)['data']['previous_status'] === 'new');
        expect($hook->fresh()->failures)->toBe(0);
    });

    it('only sends events the endpoint asked for, and only for its own restaurant', function () {
        Http::swap(new Factory);
        Http::fake(['8.8.8.8/*' => Http::response('ok', 200)]);
        [$r, $p] = apShop();
        [$other, $op] = apShop();
        apHook($r, ['order.paid']);
        apHook($other, ['order.created'], 'https://1.1.1.1/x');
        apOrder($r, $p);
        Http::assertNothingSent();
        apOrder($other, $op);
        Http::assertSent(fn ($req) => $req->url() === 'https://1.1.1.1/x');
        Http::assertSentCount(1);
    });

    it('records failures, never breaks the order, and switches a broken endpoint off', function () {
        Http::swap(new Factory);
        Http::fake(['8.8.8.8/*' => Http::response('nope', 500)]);
        [$r, $p] = apShop();
        $hook = apHook($r);
        expect(apOrder($r, $p))->toBeInstanceOf(Order::class);
        $d = WebhookDelivery::withoutGlobalScopes()->first();
        expect($d->response_code)->toBe(500)->and($d->error)->toBe('HTTP 500');

        $hook->forceFill(['failures' => WebhookEndpoint::MAX_FAILURES - 1])->save();
        $job = new \App\Modules\Api\Jobs\DeliverWebhook($d->id);
        $job->withFakeQueueInteractions();
        $job->job = null;
        // the last allowed try has failed: the delivery is marked failed and the endpoint switches off
        $ref = new ReflectionMethod($job, 'giveUp');
        $ref->invoke($job, $d->fresh(), $hook->fresh());
        expect($d->fresh()->status)->toBe('failed')->and($hook->fresh()->is_active)->toBeFalse();
    });

    it('refuses private addresses, plain http, credentials and odd ports', function () {
        $g = app(UrlGuard::class);
        foreach (['http://8.8.8.8/x', 'https://127.0.0.1/x', 'https://10.0.0.5/x', 'https://192.168.1.1/x', 'https://169.254.169.254/latest', 'https://[::1]/x', 'https://user:pw@8.8.8.8/x', 'https://8.8.8.8:6379/x', 'ftp://8.8.8.8/x', 'not a url'] as $bad) {
            expect($g->allowed($bad))->toBeFalse($bad);
        }
        expect($g->allowed('https://8.8.8.8/hook'))->toBeTrue();
    });

    it('does not follow redirects to internal addresses', function () {
        Http::swap(new Factory);
        Http::fake(['8.8.8.8/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/'])]);
        [$r, $p] = apShop();
        apHook($r);
        apOrder($r, $p);
        Http::assertSentCount(1);
        expect(WebhookDelivery::withoutGlobalScopes()->first()->response_code)->toBe(302);
    });
});

describe('panel', function () {
    it('creates a token shown once, a webhook with a secret shown once, and revokes', function () {
        [$r] = apShop();
        $owner = apUser($r, 'restaurant_owner');
        $this->actingAs($owner)->get(route('integrations.index'))->assertOk()->assertSee('API tokens');

        $this->actingAs($owner)->post(route('integrations.tokens.store'), ['name' => 'Website', 'abilities' => ['menu:read']])->assertRedirect()->assertSessionHas('new_token');
        $this->actingAs($owner)->post(route('integrations.tokens.store'), ['name' => 'None', 'abilities' => []])->assertSessionHasErrors('abilities');
        $token = app(TenantContext::class)->runAs($r, fn () => ApiToken::first());
        $this->actingAs($owner)->get(route('integrations.index'))->assertSee($token->prefix);
        $this->actingAs($owner)->delete(route('integrations.tokens.destroy', $token->id))->assertRedirect();
        expect(ApiToken::withoutGlobalScopes()->count())->toBe(0);

        $this->actingAs($owner)->post(route('integrations.webhooks.store'), ['url' => 'https://8.8.8.8/h', 'events' => ['order.paid']])->assertSessionHas('new_secret');
        $this->actingAs($owner)->post(route('integrations.webhooks.store'), ['url' => 'https://127.0.0.1/h', 'events' => ['order.paid']])->assertSessionHasErrors('url');
        $this->actingAs($owner)->post(route('integrations.webhooks.store'), ['url' => 'https://8.8.8.8/h', 'events' => ['bogus']])->assertSessionHasErrors('events.0');
        $hook = WebhookEndpoint::withoutGlobalScopes()->first();
        expect($hook->secret)->toStartWith('whsec_')->and(DB::table('webhook_endpoints')->value('secret'))->not->toContain('whsec_');
    });

    it('is for people who may manage the API, on plans that include it', function () {
        [$r] = apShop();
        $this->actingAs(apUser($r, 'waiter'))->get(route('integrations.index'))->assertForbidden();
        $this->actingAs(apUser($r, 'manager'))->get(route('integrations.index'))->assertForbidden();
        [$basic] = apShop(false);
        $owner = apUser($basic, 'restaurant_owner');
        $this->actingAs($owner)->get(route('integrations.index'))->assertOk()->assertSee('does not include');
        $this->actingAs($owner)->post(route('integrations.tokens.store'), ['name' => 'x', 'abilities' => ['menu:read']])->assertForbidden();
    });
});

describe('orders, reservations and customers through the api', function () {
    it('places an order with guest rules, once per idempotency key', function () {
        [$r, $p] = apShop();
        $h = ['Authorization' => 'Bearer '.apToken($r), 'Idempotency-Key' => 'abc-123'];
        $body = ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'Api Guest', 'customer_phone' => '+1 555 000 1111', 'lines' => [['product_id' => $p->id, 'qty' => 3]]];
        $first = $this->postJson('/api/v1/orders', $body, $h)->assertCreated()->assertJsonPath('order.total_cents', 3000)->assertJsonPath('order.source', 'api')->json('order.id');
        $this->postJson('/api/v1/orders', $body, $h)->assertCreated()->assertJsonPath('order.id', $first);
        expect(app(TenantContext::class)->runAs($r, fn () => Order::count()))->toBe(1);

        $this->postJson('/api/v1/orders', ['type' => 'takeaway', 'lines' => [['product_id' => 999999, 'qty' => 1]], 'customer_name' => 'x', 'customer_phone' => '+1 555 000 1111'], ['Authorization' => $h['Authorization']])->assertUnprocessable();
        $this->postJson('/api/v1/orders', ['type' => 'teleport', 'lines' => []], ['Authorization' => $h['Authorization']])->assertUnprocessable();
        $this->postJson('/api/v1/orders', $body, ['Authorization' => 'Bearer '.apToken($r, ['orders:read'])])->assertForbidden();
    });

    it('books, lists and moves reservations, and tells webhooks about them', function () {
        Http::swap(new Factory);
        Http::fake(['8.8.8.8/*' => Http::response('ok', 200)]);
        [$r] = apShop();
        app(\App\Modules\Core\Services\SettingsService::class)->set('reservations.config', json_encode(['enabled' => true, 'auto_confirm' => false]), $r->id);
        $hook = app(TenantContext::class)->runAs($r, function () {
            $e = new WebhookEndpoint(['url' => 'https://8.8.8.8/hook', 'events' => ['reservation.created', 'reservation.status_changed']]);
            $e->secret = 'whsec_t';
            $e->save();

            return $e;
        });
        $h = ['Authorization' => 'Bearer '.apToken($r, ['reservations:read', 'reservations:write'])];
        $date = now()->addDays(2)->format('Y-m-d');

        $res = $this->postJson('/api/v1/reservations', ['name' => 'Rita', 'phone' => '+1 555 222 3333', 'party_size' => 2, 'date' => $date, 'time' => '19:00'], $h);
        $id = $res->assertCreated()->json('reservation.id');
        $this->getJson('/api/v1/reservations', $h)->assertOk()->assertJsonPath('data.0.id', $id);
        $this->postJson("/api/v1/reservations/{$id}/status", ['status' => 'confirmed'], $h)->assertOk()->assertJsonPath('reservation.status', 'confirmed');
        $this->postJson("/api/v1/reservations/{$id}/status", ['status' => 'pending'], $h)->assertUnprocessable();
        Http::assertSent(fn ($q) => ($q->header('X-Webhook-Event')[0] ?? '') === 'reservation.created');
        Http::assertSent(fn ($q) => ($q->header('X-Webhook-Event')[0] ?? '') === 'reservation.status_changed');
    });

    it('lists and searches customers of this restaurant only', function () {
        [$r] = apShop();
        [$other] = apShop();
        app(TenantContext::class)->runAs($r, fn () => \App\Modules\Marketing\Models\Customer::create(['name' => 'Alice Smith', 'email' => 'alice@example.com', 'marketing_opt_in' => true]));
        app(TenantContext::class)->runAs($other, fn () => \App\Modules\Marketing\Models\Customer::create(['name' => 'Alice Other', 'email' => 'other@example.com']));
        $h = ['Authorization' => 'Bearer '.apToken($r)];
        $this->getJson('/api/v1/customers', $h)->assertForbidden(); // default test token has no customers:read
        $h = ['Authorization' => 'Bearer '.apToken($r, ['customers:read'])];
        $this->getJson('/api/v1/customers?q=alice', $h)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'alice@example.com');
    });

    it('publishes an OpenAPI description that lists every route', function () {
        $spec = $this->getJson('/api/v1/openapi.json')->assertOk()->json();
        expect($spec['openapi'])->toStartWith('3.')->and(array_keys($spec['paths']))->toContain('/orders', '/reservations', '/customers', '/products/{id}');
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/') && $r->uri() !== 'api/v1/openapi.json')->map(fn ($r) => preg_replace('#^api/v1#', '', preg_replace('/\{(\w+)\}/', '{id}', $r->uri())));
        foreach ($routes as $uri) {
            expect($spec['paths'])->toHaveKey($uri === '' ? '/' : $uri);
        }
    });
});
