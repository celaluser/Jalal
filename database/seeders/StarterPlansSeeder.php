<?php

namespace Database\Seeders;

use App\Modules\Billing\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Two plans to start from, created by the installer only when the platform has no plan at all (never again after
 * the owner deleted or renamed them). Without any plan a new restaurant has no working menu, so a fresh install
 * would look broken. Prices, limits and features are meant to be edited under Admin, Plans.
 */
class StarterPlansSeeder extends Seeder
{
    public function run(): void
    {
        if (Plan::withTrashed()->exists()) {
            return;
        }

        Plan::create([
            'name' => 'Free', 'slug' => 'free', 'interval' => 'lifetime', 'price' => 0, 'currency_code' => 'USD', 'is_active' => true, 'sort' => 1,
            'limits' => ['branches' => 1, 'tables' => 10, 'products' => 40, 'categories' => 8, 'staff' => 2],
            'features' => array_fill_keys(Plan::FEATURES, false),
        ]);

        Plan::create([
            'name' => 'Pro', 'slug' => 'pro', 'interval' => 'monthly', 'price' => 19, 'currency_code' => 'USD', 'is_active' => true, 'is_featured' => true, 'sort' => 2, 'trial_days' => 14,
            'limits' => [],
            'features' => array_fill_keys(Plan::FEATURES, true),
        ]);
    }
}
