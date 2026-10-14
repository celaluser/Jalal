<?php

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Mail::fake();
});

function oeShop(): array
{
    $r = Restaurant::create(['name' => 'Mail Bistro', 'slug' => 'mb'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $d = app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);

        return ['soda' => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Soda'], 'price' => 2.5, 'sort' => 1]), 'table' => DiningTable::create(['name' => 'T1'])];
    });

    return [$r, $d];
}

function oePlace(Restaurant $r, array $d, array $over = []): Order
{
    return app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, array_merge([
        'type' => 'dine_in', 'table_id' => $d['table']->id, 'payment_method' => 'cash', 'lines' => [['product_id' => $d['soda']->id, 'qty' => 1]],
    ], $over)));
}

function oeMove(Restaurant $r, Order $o, string $to, ?string $reason = null): void
{
    app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->transition($o->fresh(), $to, null, $reason));
}

it('stores the e-mail lower-cased and sends the received mail with a status link', function () {
    [$r, $d] = oeShop();
    $order = oePlace($r, $d, ['customer_email' => ' Guest@Example.COM ', 'customer_name' => 'Gus']);

    expect($order->customer_email)->toBe('guest@example.com');
    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'order_received' && $m->hasTo('guest@example.com') && $m->vars['status_url'] === $r->publicUrl('order/'.$order->token) && $m->vars['number'] === '#'.$order->number);
});

it('sends nothing without an e-mail address', function () {
    [$r, $d] = oeShop();
    oePlace($r, $d);
    Mail::assertNothingSent();
});

it('rejects a malformed address', function () {
    [$r, $d] = oeShop();
    expect(fn () => oePlace($r, $d, ['customer_email' => 'not-an-email']))->toThrow(OrderException::class);
});

it('e-mails when the order is ready, once, and not for other steps', function () {
    [$r, $d] = oeShop();
    $order = oePlace($r, $d, ['customer_email' => 'g@example.com']);
    oeMove($r, $order, OrderStatus::ACCEPTED);
    oeMove($r, $order, OrderStatus::PREPARING);
    Mail::assertSent(TemplatedMail::class, 1); // only the "received" one so far

    oeMove($r, $order, OrderStatus::READY);
    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'order_ready' && $m->hasTo('g@example.com'));
    Mail::assertSent(TemplatedMail::class, 2);
});

it('tells the guest when the restaurant cancels, but not when they cancel themselves', function () {
    [$r, $d] = oeShop();
    $mine = oePlace($r, $d, ['customer_email' => 'a@example.com']);
    oeMove($r, $mine, OrderStatus::CANCELLED, __('orders.cancelled_by_guest'));
    Mail::assertNotSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'order_cancelled');

    $theirs = oePlace($r, $d, ['customer_email' => 'b@example.com']);
    oeMove($r, $theirs, OrderStatus::CANCELLED, 'Kitchen closed');
    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'order_cancelled' && $m->hasTo('b@example.com') && $m->vars['reason'] === 'Kitchen closed');
});

it('accepts the address from the guest checkout request', function () {
    [$r, $d] = oeShop();
    $this->postJson("/r/{$r->slug}/order", [
        'type' => 'dine_in', 'table_id' => $d['table']->id, 'payment_method' => 'cash', 'customer_email' => 'web@example.com',
        'lines' => [['product_id' => $d['soda']->id, 'qty' => 1]],
    ])->assertCreated();

    Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'order_received' && $m->hasTo('web@example.com'));
    $this->postJson("/r/{$r->slug}/order", [
        'type' => 'dine_in', 'table_id' => $d['table']->id, 'payment_method' => 'cash', 'customer_email' => 'nope',
        'lines' => [['product_id' => $d['soda']->id, 'qty' => 1]],
    ])->assertStatus(422)->assertJsonPath('error', 'email_invalid');
});

it('does not break the order when the mail transport fails', function () {
    [$r, $d] = oeShop();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));

    $order = oePlace($r, $d, ['customer_email' => 'g@example.com']);
    expect($order->exists)->toBeTrue();
});
