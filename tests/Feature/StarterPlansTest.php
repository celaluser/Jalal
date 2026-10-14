<?php

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\PlanOnboarding;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\StarterPlansSeeder;

it('gives a fresh install a free and a trial plan so new restaurants get a working menu', function () {
    $this->seed(StarterPlansSeeder::class);

    expect(Plan::pluck('slug')->all())->toBe(['free', 'pro'])
        ->and(Plan::where('slug', 'free')->first()->isFree())->toBeTrue()
        ->and(Plan::where('slug', 'pro')->first()->trial_days)->toBe(14)
        ->and(app(PlanOnboarding::class)->defaultPlan()->slug)->toBe('pro');

    $r = Restaurant::create(['name' => 'Fresh', 'slug' => 'fresh', 'locale' => 'en', 'currency_code' => 'USD', 'timezone' => 'UTC']);
    expect(app(PlanOnboarding::class)->activate($r, Plan::where('slug', 'pro')->first()))->toBe(PlanOnboarding::TRIAL);
});

it('never recreates plans the owner changed or deleted', function () {
    $this->seed(StarterPlansSeeder::class);
    Plan::where('slug', 'free')->first()->delete();
    Plan::where('slug', 'pro')->first()->update(['price' => 99]);

    $this->seed(StarterPlansSeeder::class);

    expect(Plan::count())->toBe(1)->and((float) Plan::first()->price)->toBe(99.0);
});

it('leaves a platform that already has its own plans alone', function () {
    Plan::create(['name' => 'Mine', 'slug' => 'mine', 'interval' => 'monthly', 'price' => 5, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    $this->seed(StarterPlansSeeder::class);

    expect(Plan::pluck('slug')->all())->toBe(['mine']);
});
