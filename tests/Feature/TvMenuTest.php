<?php

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Tenancy\Models\Restaurant;

beforeEach(function () {
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

function tvShop(bool $subscribed = true): Restaurant
{
    $r = Restaurant::create(['name' => 'Screen Cafe', 'slug' => 'tv'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);

    if ($subscribed) {
        $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
        app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    }

    return $r;
}

it('shows a rotating full-screen menu with sale offers first and pages long categories', function () {
    $r = tvShop();
    app(TenantContext::class)->runAs($r, function () {
        $c = Category::create(['name' => 'Starters', 'is_active' => true]);
        foreach (range(1, 7) as $i) {
            Product::create(['category_id' => $c->id, 'name' => "Dish $i", 'price' => 5 + $i, 'is_active' => true]);
        }
        Product::create(['category_id' => $c->id, 'name' => 'Deal Soup', 'price' => 4, 'compare_price' => 8, 'is_active' => true]);
    });

    $html = $this->get(route('storefront.tv', $r->slug).'?per=4&s=7')->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->getContent();

    expect($html)->toContain('Screen Cafe')->toContain(e(__('tv.offers')))->toContain('Deal Soup')->toContain('data-seconds="7"')->toContain('Starters')->toContain('1/2')->toContain('2/2')
        ->and(substr_count($html, 'class="tv-slide'))->toBe(3);
});

it('keeps slide timing inside safe bounds and handles an empty menu', function () {
    $r = tvShop();

    $this->get(route('storefront.tv', $r->slug).'?s=1&per=999')->assertOk()->assertSee('data-seconds="5"', false)->assertSee(__('tv.empty'));
});

it('is not available without an active plan', function () {
    $this->get(route('storefront.tv', tvShop(false)->slug))->assertNotFound();
});
