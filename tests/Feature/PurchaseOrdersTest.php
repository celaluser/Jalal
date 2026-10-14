<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Inventory\Mail\PurchaseOrderMail;
use App\Modules\Inventory\Models\Ingredient;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Inventory\Services\PurchaseOrders;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

function poShop(): array
{
    $r = Restaurant::create(['name' => 'Order Bistro', 'slug' => 'po'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $user = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $user->assignRole(Permissions::OWNER);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));

    $data = app(TenantContext::class)->runAs($r, function () {
        $a = Supplier::create(['name' => 'Fresh Farms', 'email' => 'farm@example.test']);
        $b = Supplier::create(['name' => 'No Mail Co']);

        return [
            Ingredient::create(['name' => 'Flour', 'unit' => 'kg', 'stock_qty' => 3, 'low_at' => 10, 'unit_cost' => 2, 'supplier_id' => $a->id]),
            Ingredient::create(['name' => 'Cheese', 'unit' => 'kg', 'stock_qty' => 50, 'low_at' => 10, 'supplier_id' => $a->id]),
            Ingredient::create(['name' => 'Salt', 'unit' => 'kg', 'stock_qty' => 0, 'low_at' => 1, 'supplier_id' => $b->id]),
            Ingredient::create(['name' => 'Loose', 'unit' => 'pcs', 'stock_qty' => 1, 'low_at' => 5]),
            $a, $b,
        ];
    });

    return [$r, $user, ...$data];
}

it('groups low items by supplier with a suggested quantity back to twice the warning level', function () {
    [$r, , $flour, , , , $farm] = poShop();

    $groups = app(TenantContext::class)->runAs($r, fn () => app(PurchaseOrders::class)->groups());

    expect($groups->keys()->all())->toContain($farm->id, 0)->and($groups)->toHaveCount(3);
    $line = $groups[$farm->id]['lines'][0];
    expect($line['name'])->toBe('Flour')->and($line['qty'])->toBe(17.0)->and($line['est'])->toBe(34.0)->and(count($groups[$farm->id]['lines']))->toBe(1);
});

it('shows the page, downloads a PDF and refuses other suppliers items', function () {
    [$r, $owner, $flour, $cheese, , , $farm, $nomail] = poShop();

    $this->actingAs($owner)->get(route('inventory.orders'))->assertOk()->assertSee('Fresh Farms')->assertSee('Flour')->assertDontSee('Cheese');
    $res = $this->actingAs($owner)->post(route('inventory.orders.pdf'), ['supplier_id' => $farm->id, 'qty' => [$flour->id => 5]]);
    $res->assertOk()->assertHeader('content-type', 'application/pdf');

    // Cheese is not low and belongs to the same supplier; a forged id of another supplier's item yields nothing.
    $this->actingAs($owner)->post(route('inventory.orders.pdf'), ['supplier_id' => $nomail->id, 'qty' => [$flour->id => 5]])->assertStatus(422);
});

it('e-mails the order with a PDF attached, and explains when the supplier has no address', function () {
    Mail::fake();
    [$r, $owner, $flour, , , , $farm, $nomail] = poShop();

    $this->actingAs($owner)->post(route('inventory.orders.mail'), ['supplier_id' => $farm->id, 'qty' => [$flour->id => 12.5], 'note' => 'Morning please'])->assertSessionHas('status');
    Mail::assertSent(PurchaseOrderMail::class, function (PurchaseOrderMail $m) use ($flour) {
        return $m->hasTo('farm@example.test') && $m->lines[0]['qty'] == 12.5 && count($m->attachments()) === 1 && $m->note === 'Morning please';
    });

    $salt = Ingredient::withoutGlobalScopes()->where('name', 'Salt')->first();
    $this->actingAs($owner)->post(route('inventory.orders.mail'), ['supplier_id' => $nomail->id, 'qty' => [$salt->id => 2]])->assertSessionHasErrors('supplier_id');
    Mail::assertSent(PurchaseOrderMail::class, 1);
});

it('is closed to staff without inventory rights', function () {
    [$r] = poShop();
    $waiter = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $waiter->assignRole(Permissions::WAITER);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));

    $this->actingAs($waiter)->get(route('inventory.orders'))->assertForbidden();
});
