<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\OrderSettings;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

function voShop(array $settings = []): array
{
    $r = Restaurant::create(['name' => 'View Bistro', 'slug' => 'vo'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now(), 'order_settings' => $settings]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $product = app(TenantContext::class)->runAs($r, function () {
        $c = Category::create(['name' => 'Mains', 'is_active' => true]);

        return Product::create(['category_id' => $c->id, 'name' => 'Kebab', 'price' => 12, 'is_active' => true]);
    });
    $user = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $user->assignRole(Permissions::OWNER);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return [$r, $product, $user];
}

it('shows the ordering invitation and cart when online ordering is on', function () {
    [$r] = voShop();

    expect(app(OrderSettings::class)->viewOnly($r))->toBeFalse();
    $this->get(route('storefront.menu', $r->slug))->assertOk()->assertSee(__('customer.cta_text'))->assertDontSee(__('orders.view_only_title'));
});

it('turns the menu view-only: prices stay, the guest is told to ask the waiter, orders are refused', function () {
    [$r, $product] = voShop(['online_ordering' => false, 'view_message' => 'Tell Ayşe what you want']);

    expect(app(OrderSettings::class)->viewOnly($r))->toBeTrue()->and(app(OrderSettings::class)->accepting($r))->toBeFalse();
    $this->get(route('storefront.menu', $r->slug))->assertOk()
        ->assertSee(__('orders.view_only_title'))->assertSee('Tell Ayşe what you want')->assertSee('Kebab')->assertSee('view-only', false)
        ->assertDontSee(__('customer.cta_text'));

    expect(fn () => app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $product->id, 'qty' => 1]]])))->toThrow(OrderException::class);

    // Staff can still key an order in for the table.
    $order = app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, ['type' => 'takeaway', 'payment_method' => 'cash', 'lines' => [['product_id' => $product->id, 'qty' => 1]]], 'staff'));
    expect($order->exists)->toBeTrue();
});

it('lets the owner switch it off without choosing an order type or payment', function () {
    [$r, , $owner] = voShop();

    $this->actingAs($owner)->get(route('orders.settings'))->assertOk()->assertSee(__('orders.online_ordering'));
    $this->actingAs($owner)->put(route('orders.settings.update'), ['view_message' => 'Ask us'])->assertSessionHasNoErrors();

    $fresh = Restaurant::find($r->id);
    expect(app(OrderSettings::class)->viewOnly($fresh))->toBeTrue()->and($fresh->order_settings['view_message'])->toBe('Ask us');
});
