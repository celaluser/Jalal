<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Branches\Models\Branch;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Services\Chat;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

function chShop(): array
{
    $r = Restaurant::create(['name' => 'Chat Bistro', 'slug' => 'ch'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $product = app(TenantContext::class)->runAs($r, fn () => Product::create(['category_id' => Category::create(['name' => ['en' => 'Food'], 'sort' => 1])->id, 'name' => ['en' => 'Soup'], 'price' => 5, 'sort' => 1]));

    return [$r, chUser($r, Permissions::OWNER), $product];
}

function chUser(Restaurant $r, string $role, array $attrs = []): User
{
    $user = User::factory()->create(['restaurant_id' => $r->id] + $attrs);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $user;
}

function chOrder(Restaurant $r, Product $p, array $extra = []): Order
{
    return app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, $extra + ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'Gus', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $p->id, 'qty' => 1]]]));
}

it('lets a guest write from the order page and the staff answer', function () {
    [$r, $owner, $p] = chShop();
    $order = chOrder($r, $p);
    $url = '/r/'.$r->slug.'/order/'.$order->token.'/chat';

    $this->get('/r/'.$r->slug.'/order/'.$order->token)->assertOk()->assertSee(__('chat.guest_title'));
    $this->postJson($url, ['body' => "  Is the soup\n vegan? <script>alert(1)</script> "])->assertCreated()->assertJsonPath('message.from', 'guest');
    expect(ChatMessage::first()->body)->toBe('Is the soup vegan? alert(1)');

    // The inbox counts it as unread, the conversation marks it read.
    $this->actingAs($owner)->get(route('chat.index'))->assertOk()->assertSee('Is the soup vegan?')->assertSee('#'.$order->number);
    expect(app(Chat::class)->unreadForStaff())->toBe(1);
    $this->actingAs($owner)->get(route('chat.show', $order->id))->assertOk()->assertSee('Is the soup vegan?');
    expect(app(Chat::class)->unreadForStaff())->toBe(0);

    $this->actingAs($owner)->postJson(route('chat.reply', $order->id), ['body' => 'Yes, it is vegan.'])->assertCreated()->assertJsonPath('message.from', 'staff');

    $poll = $this->getJson($url.'?after=0&seen=1')->assertOk()->json();
    expect(collect($poll['messages'])->pluck('from')->all())->toBe(['guest', 'staff'])->and($poll['open'])->toBeTrue();
    expect(ChatMessage::where('sender', 'staff')->first()->guest_read_at)->not->toBeNull();
    expect($this->getJson($url.'?after='.$poll['messages'][1]['id'])->json('messages'))->toBe([]);
});

it('keeps conversations to the order token, the chat switch and finished orders', function () {
    [$r, $owner, $p] = chShop();
    $order = chOrder($r, $p);
    $other = chOrder($r, $p);
    $url = '/r/'.$r->slug.'/order/'.$order->token.'/chat';

    $this->postJson($url, [])->assertStatus(422);
    $this->postJson($url, ['body' => str_repeat('x', 501)])->assertStatus(422);
    $this->getJson('/r/'.$r->slug.'/order/'.str_repeat('a', 24).'/chat')->assertNotFound();
    $this->postJson($url, ['body' => 'hello'])->assertCreated();
    expect(ChatMessage::where('order_id', $other->id)->count())->toBe(0);

    // A cancelled order, or one finished long ago, cannot be written to.
    app(TenantContext::class)->runAs($r, fn () => Order::whereKey($order->id)->update(['status' => 'cancelled']));
    $this->postJson($url, ['body' => 'again'])->assertForbidden();

    // Switched off: the guest endpoints and the box disappear.
    app(Chat::class)->setEnabled($r, false);
    $this->getJson('/r/'.$r->slug.'/order/'.$other->token.'/chat')->assertNotFound();
    $this->get('/r/'.$r->slug.'/order/'.$other->token)->assertOk()->assertDontSee(__('chat.guest_title'));
    $this->actingAs($owner)->get(route('chat.index'))->assertOk()->assertSee(__('chat.is_off'));
});

it('keeps the inbox to staff of this restaurant, and to their own branch', function () {
    [$r, $owner, $p] = chShop();
    [, $strangerOwner] = chShop();
    [$north, $south] = app(TenantContext::class)->runAs($r, fn () => [Branch::create(['name' => 'North', 'slug' => 'north', 'sort' => 1]), Branch::create(['name' => 'South', 'slug' => 'south', 'sort' => 2])]);
    $a = chOrder($r, $p, ['branch_id' => $north->id]);
    $b = chOrder($r, $p, ['branch_id' => $south->id]);
    foreach ([$a, $b] as $o) {
        $this->postJson('/r/'.$r->slug.'/order/'.$o->token.'/chat', ['body' => 'hi from '.$o->number])->assertCreated();
    }

    $this->actingAs($strangerOwner)->get(route('chat.show', $a->id))->assertNotFound();
    $this->actingAs($strangerOwner)->postJson(route('chat.reply', $a->id), ['body' => 'x'])->assertNotFound();

    $northStaff = chUser($r, Permissions::WAITER, ['branch_id' => $north->id]);
    $this->actingAs($northStaff)->get(route('chat.index'))->assertOk()->assertSee('hi from '.$a->number)->assertDontSee('hi from '.$b->number);
    $this->actingAs($northStaff)->get(route('chat.show', $b->id))->assertNotFound();
    $this->actingAs($northStaff)->postJson(route('chat.reply', $b->id), ['body' => 'x'])->assertNotFound();
    expect(app(Chat::class)->unreadForStaff($north->id))->toBe(1);

    $this->actingAs(chUser($r, Permissions::DELIVERY))->get(route('chat.index'))->assertForbidden();
});
