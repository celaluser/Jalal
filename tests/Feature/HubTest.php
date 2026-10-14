<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Hub\Models\OrderChannel;
use App\Modules\Hub\Support\ChannelAdapter;
use App\Modules\Hub\Support\ChannelRegistry;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Cache::flush();
});

function hubShop(): array
{
    $r = Restaurant::create(['name' => 'Hub Bistro', 'slug' => 'hub'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en', 'tr'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now(), 'order_settings' => ['delivery' => true]]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]), now()->addMonth());
    $owner = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $owner->assignRole(Permissions::OWNER);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);

        return ['pizza' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Margherita', 'tr' => 'Margarita'], 'price' => 12, 'sort' => 1]), 'cola' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Cola'], 'price' => 3, 'sort' => 2])];
    });

    return [$r, $owner, $d];
}

/** Makes a channel through the panel and returns it with the secret address shown once. */
function hubChannel($test, User $owner, string $name = 'Yemeksepeti'): array
{
    $test->actingAs($owner)->post(route('channels.store'), ['name' => $name, 'kind' => 'generic'])->assertRedirect();
    $url = session('channel_secret.url');
    $channel = OrderChannel::allTenants()->latest('id')->first();

    return [$channel, $url];
}

function hubPayload(array $over = []): array
{
    return $over + ['external_id' => 'YS-1', 'type' => 'delivery', 'customer_name' => 'Ayşe', 'customer_phone' => '+90 555 111 22 33', 'delivery_address' => 'Moda Cd. 12/3', 'lines' => [['name' => 'margherita', 'qty' => 2], ['name' => 'Cola']]];
}

it('lets the owner make a channel and shows its secret address once', function () {
    [$r, $owner] = hubShop();
    [$channel, $url] = hubChannel($this, $owner);

    expect($url)->toStartWith(url('hub').'/')->and($channel->token_hash)->not->toBe(substr($url, -40))->and(strlen($channel->token_hash))->toBe(64);
    $this->actingAs($owner)->get(route('channels.index'))->assertOk()->assertSee(substr($url, -40)); // shown right after creating it
    $this->actingAs($owner)->get(route('channels.index'))->assertOk()->assertSee('Yemeksepeti')->assertDontSee(substr($url, -40)); // never again
    $this->actingAs(User::factory()->create(['restaurant_id' => $r->id]))->get(route('channels.index'))->assertForbidden();
});

it('turns a platform order into a normal order on the kitchen board, priced from the menu', function () {
    [$r, $owner, $d] = hubShop();
    [$channel, $url] = hubChannel($this, $owner);

    $res = $this->postJson($url, hubPayload(['paid' => true]))->assertCreated()->assertJsonPath('duplicate', false);
    $order = app(TenantContext::class)->runAs($r, fn () => Order::with('items')->find($res->json('order.id')));

    expect($order->source)->toBe('hub')->and($order->channel_id)->toBe($channel->id)->and($order->external_id)->toBe('YS-1')
        ->and($order->items->sum('qty'))->toBe(3)->and($order->isPaid())->toBeTrue()->and($order->status)->toBe('accepted')
        ->and($order->note)->toContain('Yemeksepeti #YS-1');
    expect($order->subtotal_cents)->toBe(2700); // 2 x 12 + 3, whatever the platform thinks
    expect($channel->fresh()->orders_count)->toBe(1);

    $this->actingAs($owner)->get(route('orders.show', $order->id))->assertOk()->assertSee('Yemeksepeti');
    // A Turkish name finds the same dish.
    $this->postJson($url, hubPayload(['external_id' => 'YS-2', 'lines' => [['name' => 'Margarita']]]))->assertCreated();
});

it('treats the same platform order sent twice as one order', function () {
    [$r, $owner] = hubShop();
    [, $url] = hubChannel($this, $owner);

    $first = $this->postJson($url, hubPayload())->assertCreated();
    $again = $this->postJson($url, hubPayload())->assertOk()->assertJsonPath('duplicate', true);

    expect($again->json('order.id'))->toBe($first->json('order.id'))->and(Order::allTenants()->count())->toBe(1);
});

it('refuses unknown dishes, bad data, unknown or paused channels', function () {
    [$r, $owner] = hubShop();
    [$channel, $url] = hubChannel($this, $owner);

    $this->postJson($url, hubPayload(['lines' => [['name' => 'Sushi platter']]]))->assertStatus(422)->assertJsonPath('code', 'unmatched')->assertJsonPath('detail', 'Sushi platter');
    $this->postJson($url, ['type' => 'delivery'])->assertStatus(422)->assertJsonPath('code', 'invalid');
    expect(Order::allTenants()->count())->toBe(0)->and($channel->fresh()->last_error)->not->toBeNull();

    $this->postJson(route('hub.receive', str_repeat('a', 40)), hubPayload())->assertNotFound();

    $this->actingAs($owner)->post(route('channels.toggle', $channel->id));
    $this->postJson($url, hubPayload())->assertNotFound();
    $this->actingAs($owner)->post(route('channels.toggle', $channel->id));
    $this->postJson($url, hubPayload())->assertCreated();
});

it('keeps channels of different restaurants apart and a renewed address replaces the old one', function () {
    [$r1, $owner1] = hubShop();
    [$r2, $owner2] = hubShop();
    [$c1, $url1] = hubChannel($this, $owner1, 'One');
    [$c2, $url2] = hubChannel($this, $owner2, 'Two');

    $this->postJson($url1, hubPayload())->assertCreated();
    expect(Order::allTenants()->where('restaurant_id', $r2->id)->count())->toBe(0);
    $this->actingAs($owner2)->post(route('channels.renew', $c1->id))->assertNotFound(); // not theirs

    $this->actingAs($owner1)->post(route('channels.renew', $c1->id))->assertRedirect();
    $new = session('channel_secret.url');
    expect($new)->not->toBe($url1);
    $this->postJson($url1, hubPayload(['external_id' => 'X']))->assertNotFound();
    $this->postJson($new, hubPayload(['external_id' => 'X']))->assertCreated();

    $this->actingAs($owner1)->delete(route('channels.destroy', $c1->id))->assertRedirect();
    $this->postJson($new, hubPayload(['external_id' => 'Y']))->assertNotFound();
});

it('reads another platform through a registered adapter', function () {
    [$r, $owner] = hubShop();
    ChannelRegistry::register(new class implements ChannelAdapter
    {
        public function kind(): string
        {
            return 'acme';
        }

        public function label(): string
        {
            return 'Acme Eats';
        }

        public function normalize(array $raw): array
        {
            return ['external_id' => $raw['id'], 'type' => 'takeaway', 'customer_name' => $raw['who'], 'lines' => array_map(fn ($i) => ['name' => $i['dish'], 'qty' => $i['n']], $raw['items'])];
        }
    });

    $this->actingAs($owner)->post(route('channels.store'), ['name' => 'Acme', 'kind' => 'acme'])->assertRedirect();
    $this->postJson(session('channel_secret.url'), ['id' => 'A-9', 'who' => 'Bo', 'items' => [['dish' => 'Cola', 'n' => 4]]])->assertCreated();

    expect(Order::allTenants()->first()->external_id)->toBe('A-9');
    $this->actingAs($owner)->post(route('channels.store'), ['name' => 'Nope', 'kind' => 'missing'])->assertSessionHasErrors('kind');
    ChannelRegistry::flush();
});
