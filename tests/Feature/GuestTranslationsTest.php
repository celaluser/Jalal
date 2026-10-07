<?php

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Arr;

beforeEach(function () {
    foreach (['en' => 'English', 'tr' => 'Türkçe', 'ar' => 'العربية'] as $code => $name) {
        Language::firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true, 'is_default' => $code === 'en', 'is_rtl' => $code === 'ar']);
    }
});

/** Dotted keys (without trailing wildcards) of a translation file. */
function gtKeys(string $file, string $locale): array
{
    return array_keys(Arr::dot(require base_path("lang/{$locale}/{$file}.php")));
}

it('translates every customer string into Turkish and Arabic with the same placeholders', function (string $locale) {
    $en = Arr::dot(require base_path('lang/en/customer.php'));
    $tr = Arr::dot(require base_path("lang/{$locale}/customer.php"));

    expect(array_keys($tr))->toEqualCanonicalizing(array_keys($en));
    foreach ($en as $key => $text) {
        preg_match_all('/:[a-z_]+/', $text, $a);
        preg_match_all('/:[a-z_]+/', $tr[$key], $b);
        expect($b[0])->toEqualCanonicalizing($a[0], "placeholders differ in {$locale} customer.{$key}");
    }
})->with(['tr', 'ar']);

it('covers all allergen and diet labels', function (string $locale) {
    $en = Arr::dot(Arr::only(require base_path('lang/en/menu.php'), ['allergen', 'diet']));
    $other = Arr::dot(require base_path("lang/{$locale}/menu.php"));

    expect(array_keys($other))->toEqualCanonicalizing(array_keys($en));
})->with(['tr', 'ar']);

it('only translates guest order strings that exist in English, with the same placeholders', function (string $locale) {
    $en = Arr::dot(require base_path('lang/en/orders.php'));
    $other = Arr::dot(require base_path("lang/{$locale}/orders.php"));

    foreach ($other as $key => $text) {
        expect($en)->toHaveKey($key);
        preg_match_all('/:[a-z_]+/', $en[$key], $a);
        preg_match_all('/:[a-z_]+/', $text, $b);
        expect($b[0])->toEqualCanonicalizing($a[0], "placeholders differ in {$locale} orders.{$key}");
    }
    foreach (['type_dine_in', 'checkout', 'place', 'step_ready', 'error_closed', 'email_label'] as $key) {
        expect($other)->toHaveKey($key);
    }
})->with(['tr', 'ar']);

it('shows the public menu in the guest’s language', function () {
    $r = Restaurant::create(['name' => 'Lokanta', 'slug' => 'lok'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en', 'tr', 'ar'], 'currency_code' => 'USD', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());
    app(TenantContext::class)->runAs($r, function () {
        $c = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);
        Product::create(['category_id' => $c->id, 'name' => ['en' => 'Soup'], 'price' => 3, 'dietary' => ['vegan'], 'allergens' => ['celery'], 'sort' => 1]);
    });

    $this->withHeader('Accept-Language', 'tr')->get("/r/{$r->slug}")->assertOk()->assertSee('Yemek ara')->assertSee('Kereviz')->assertSee('lang="tr"', false);
    $this->withHeader('Accept-Language', 'ar')->get("/r/{$r->slug}")->assertOk()->assertSee('ابحث عن طبق')->assertSee('الكرفس')->assertSee('dir="rtl"', false);
});
