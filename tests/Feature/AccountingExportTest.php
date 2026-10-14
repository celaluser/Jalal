<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Orders\Models\Order;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

function accShop(): array
{
    $r = Restaurant::create(['name' => 'Books Bistro', 'slug' => 'ac'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => ['analytics' => true]]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    $user = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $user->assignRole(Permissions::OWNER);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));

    app(TenantContext::class)->runAs($r, function () use ($r) {
        $mk = fn (int $n, string $status, int $total, int $tax, ?string $name = null) => Order::unguarded(fn () => Order::create([
            'number' => $n, 'token' => substr(md5((string) $n.$r->id), 0, 24), 'type' => 'takeaway', 'status' => $status, 'source' => 'qr', 'customer_name' => $name,
            'currency_code' => 'USD', 'subtotal_cents' => $total - $tax, 'tax_cents' => $tax, 'total_cents' => $total, 'tip_cents' => 100, 'payment_method' => 'cash',
        ]));
        $mk(1, 'completed', 1200, 200, '=HYPERLINK("x")');
        $mk(2, 'completed', 600, 0, 'Ann');
        $mk(3, 'cancelled', 9999, 999, 'Gone');
    });

    return [$r, $user];
}

function accPost($test, User $u, array $over = []): string
{
    return $test->actingAs($u)->post(route('reports.accounting.download'), $over + ['format' => 'generic', 'mode' => 'orders', 'range' => 'custom', 'from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString()])->assertOk()->streamedContent();
}

it('writes a Xero sales-invoice file per order without cancelled orders', function () {
    [, $u] = accShop();
    $csv = accPost($this, $u, ['format' => 'xero', 'account' => '260', 'tax_label' => '20% (VAT on Income)']);

    expect($csv)->toContain('*ContactName,EmailAddress,*InvoiceNumber')->toContain('ORD-1')->toContain('ORD-2')->not->toContain('ORD-3')->not->toContain('9999')
        ->toContain('10.00')->toContain(',260,"20% (VAT on Income)",USD')->toContain('Tax Exempt')
        ->toContain("'=HYPERLINK"); // formulas in guest names are defused
});

it('writes a QuickBooks invoice file and a plain CSV', function () {
    [, $u] = accShop();

    $qb = accPost($this, $u, ['format' => 'quickbooks']);
    expect($qb)->toContain('InvoiceNo,Customer,InvoiceDate')->toContain('ORD-2')->toContain('Sales')->toContain('2.00');

    $plain = accPost($this, $u, ['format' => 'generic']);
    expect($plain)->toContain('Date,Number,Description,Customer,Net,Tax,Total,Tips')->toContain('12.00')->toContain('1.00');
});

it('can summarise one document per day', function () {
    [, $u] = accShop();
    $csv = accPost($this, $u, ['format' => 'xero', 'mode' => 'daily']);

    expect($csv)->toContain('SALES-'.now()->format('Ymd'))->toContain('(2 orders)')->toContain('16.00')->not->toContain('ORD-1');
});

it('rejects odd formats and unsafe codes and is closed to staff without report rights', function () {
    [$r, $u] = accShop();

    $this->actingAs($u)->post(route('reports.accounting.download'), ['format' => 'sage', 'mode' => 'orders'])->assertSessionHasErrors('format');
    $this->actingAs($u)->post(route('reports.accounting.download'), ['format' => 'xero', 'mode' => 'orders', 'account' => '=cmd|x'])->assertSessionHasErrors('account');
    $this->actingAs($u)->get(route('reports.accounting'))->assertOk()->assertSee(__('analytics.acc_title'));

    $w = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $w->assignRole(Permissions::WAITER);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->actingAs($w)->get(route('reports.accounting'))->assertForbidden();
});
