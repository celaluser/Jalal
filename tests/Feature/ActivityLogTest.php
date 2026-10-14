<?php

use App\Models\User;
use App\Modules\Activity\Models\ActivityLog;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
});

function alShop(string $role = Permissions::OWNER): array
{
    $r = Restaurant::create(['name' => 'Log Bar', 'slug' => 'lb'.uniqid(), 'locale' => 'en', 'currency_code' => 'USD', 'onboarded_at' => now()]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => [], 'features' => []]), now()->addMonth());
    $u = User::factory()->create(['restaurant_id' => $r->id, 'name' => 'Olga Owner']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    $u->assignRole($role);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return [$r, $u];
}

it('records who created, changed and deleted a dish, with the old and new value', function () {
    [$r, $owner] = alShop();
    $this->actingAs($owner);
    app(TenantContext::class)->runAs($r, function () {
        $cat = Category::create(['name' => ['en' => 'Food']]);
        $p = Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Pizza'], 'price' => 10]);
        $p->update(['price' => 12]);
        $p->delete();
    });

    $logs = ActivityLog::withoutGlobalScopes()->where('restaurant_id', $r->id)->where('subject_type', 'Product')->orderBy('id')->get();
    expect($logs->pluck('event')->all())->toBe(['created', 'updated', 'deleted'])->and($logs[1]->changes['price'])->toBe(['10.00', 12])->and($logs[0]->user_name)->toBe('Olga Owner')->and($logs[0]->label)->toBe('Pizza');
});

it('never writes secrets or timestamps into the log', function () {
    [$r, $owner] = alShop();
    $this->actingAs($owner);
    app(TenantContext::class)->runAs($r, fn () => Category::create(['name' => ['en' => 'X']])->update(['sort' => 3]));
    $log = ActivityLog::withoutGlobalScopes()->where('event', 'updated')->first();
    expect(array_keys($log->changes))->toBe(['sort']);
});

it('shows the owner their own log only', function () {
    [$r, $owner] = alShop();
    [$other, $otherOwner] = alShop();
    $this->actingAs($owner);
    app(TenantContext::class)->runAs($r, fn () => Category::create(['name' => ['en' => 'Mine']]));
    $this->actingAs($otherOwner);
    app(TenantContext::class)->runAs($other, fn () => Category::create(['name' => ['en' => 'Theirs']]));

    $this->actingAs($owner)->get(route('activity.index'))->assertOk()->assertSee('Mine')->assertDontSee('Theirs');
    $this->actingAs($owner)->get(route('activity.index', ['q' => 'nothing-here']))->assertOk()->assertDontSee('Mine');
});

it('is for owners only', function () {
    [$r] = alShop();
    foreach (['manager', 'waiter', 'kitchen'] as $role) {
        [, $u] = alShop($role);
        $this->actingAs($u)->get(route('activity.index'))->assertForbidden();
    }
});

it('logs team changes', function () {
    [$r, $owner] = alShop();
    $this->actingAs($owner)->post(route('team.store'), ['name' => 'Wendy', 'email' => 'w@example.com', 'role' => 'bar'])->assertSessionHasNoErrors();
    expect(ActivityLog::withoutGlobalScopes()->where('restaurant_id', $r->id)->where('event', 'invited')->exists())->toBeTrue();
});
