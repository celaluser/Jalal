<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Demo\Support\DemoDataRegistry;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sample data for the live demo: a platform admin and three restaurants, each with a full staff
 * team. Menus, products and orders are added by the modules that own those tables, through
 * DemoDataRegistry, so this seeder never needs to change when they arrive.
 */
class DemoSeeder extends Seeder
{
    public const RESTAURANTS = [
        ['name' => 'Bella Italia', 'slug' => 'bella-italia', 'locale' => 'en', 'currency' => 'EUR', 'theme' => 'classic', 'color' => '#c8402a', 'city' => 'Milan', 'menu_locales' => ['en', 'tr']],
        ['name' => 'Sushi Zen', 'slug' => 'sushi-zen', 'locale' => 'en', 'currency' => 'USD', 'theme' => 'midnight', 'color' => '#ff5a5f', 'city' => 'Tokyo', 'menu_locales' => ['en', 'tr']],
        ['name' => 'Kahve Durağı', 'slug' => 'kahve-duragi', 'locale' => 'tr', 'currency' => 'TRY', 'theme' => 'fresh', 'color' => '#1c8e73', 'city' => 'İstanbul', 'menu_locales' => ['tr', 'en']],
    ];

    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, LanguagesAndCurrenciesSeeder::class]);

        $password = config('demo.password');
        $registrar = app(PermissionRegistrar::class);

        $admin = $this->user(null, 'Demo Admin', 'admin@demo.test', $password);
        $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));
        $admin->syncRoles([Permissions::SUPER_ADMIN]);

        // One generous plan for every demo restaurant, so the whole product can be tried.
        $plan = Plan::firstOrCreate(['slug' => 'demo'], [
            'name' => 'Demo', 'interval' => 'lifetime', 'price' => 0, 'currency_code' => 'USD', 'is_active' => false,
            'limits' => [], 'features' => array_fill_keys(Plan::FEATURES, true),
        ]);

        foreach (self::RESTAURANTS as $data) {
            $restaurant = Restaurant::updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'locale' => $data['locale'],
                'currency_code' => $data['currency'],
                'status' => Restaurant::STATUS_ACTIVE,
                'theme' => $data['theme'],
                'city' => $data['city'],
                'menu_locales' => $data['menu_locales'],
                'branding' => ['color' => $data['color']],
                'onboarded_at' => now(),
            ]);

            app(SubscriptionService::class)->assign($restaurant, $plan);

            $registrar->setPermissionsTeamId($restaurant->id);

            foreach ([
                'owner' => [Permissions::OWNER, 'Owner'],
                'manager' => [Permissions::MANAGER, 'Manager'],
                'waiter' => [Permissions::WAITER, 'Waiter'],
                'kitchen' => [Permissions::KITCHEN, 'Kitchen'],
                'cashier' => [Permissions::CASHIER, 'Cashier'],
            ] as $key => [$role, $label]) {
                $user = $this->user($restaurant->id, "{$data['name']} {$label}", "{$key}@{$data['slug']}.demo", $password);
                $user->syncRoles([$role]);

                if ($role === Permissions::OWNER) {
                    $restaurant->update(['owner_id' => $user->id]);
                }
            }

            app(TenantContext::class)->runAs($restaurant, function () {
                foreach (DemoDataRegistry::all() as $seeder) {
                    $this->call($seeder);
                }
            });
        }
    }

    private function user(?int $restaurantId, string $name, string $email, string $password): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'restaurant_id' => $restaurantId,
            'name' => $name,
            'password' => $password,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
