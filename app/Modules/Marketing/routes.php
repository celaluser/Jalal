<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Marketing\Http\Controllers\BannerController;
use App\Modules\Marketing\Http\Controllers\CampaignController;
use App\Modules\Marketing\Http\Controllers\CustomerController;
use App\Modules\Marketing\Http\Controllers\GiftCardController;
use App\Modules\Marketing\Http\Controllers\GrowthToolsController;
use App\Modules\Marketing\Http\Controllers\GuestReviewController;
use App\Modules\Marketing\Http\Controllers\MarketingSettingsController;
use App\Modules\Marketing\Http\Controllers\PriceRuleController;
use App\Modules\Marketing\Http\Controllers\PromoController;
use App\Modules\Marketing\Http\Controllers\PushOptInController;
use App\Modules\Marketing\Http\Controllers\ReviewController;
use App\Modules\Marketing\Http\Controllers\SegmentController;
use App\Modules\Marketing\Http\Controllers\UnsubscribeController;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

// Guest: rate an order from its tracking page, on /r/{slug} and on a restaurant's own domain.
$guest = function () {
    Route::post('order/{token}/review', [GuestReviewController::class, 'store'])->where('token', '[a-z0-9]{24}')->middleware('throttle:10,1')->name('review.store');
    Route::post('offers/push', [PushOptInController::class, 'store'])->middleware('throttle:10,1')->name('push.optin');
    Route::get('links', [GrowthToolsController::class, 'links'])->name('links');
    Route::get('widget.js', [GrowthToolsController::class, 'script'])->name('widget');
};
Route::middleware(['web', SetLocale::class, ResolveTenant::class])
    ->prefix(config('tenancy.path_prefix').'/{restaurant}')->name('storefront.')->where(['restaurant' => '[A-Za-z0-9-]+'])->group($guest);
Route::middleware(['web', SetLocale::class, ResolveTenant::class])->name('storefront.host.')->group($guest);

// One-click unsubscribe from a campaign. Signed, no session: mail apps POST here without a CSRF token.
Route::middleware(['signed', 'throttle:30,1'])->prefix('unsubscribe')->name('marketing.unsubscribe')->group(function () {
    Route::get('{customer}', [UnsubscribeController::class, 'show'])->whereNumber('customer')->name('');
    Route::post('{customer}', [UnsubscribeController::class, 'store'])->whereNumber('customer')->name('.store');
});

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user'])->group(function () {
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('customers.show');
        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
    });

    Route::middleware('permission:customers.manage')->group(function () {
        Route::get('customers/export', [CustomerController::class, 'export'])->middleware('throttle:10,1')->name('customers.export');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->name('customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->whereNumber('customer')->name('customers.destroy');
    });

    Route::middleware('permission:marketing.manage')->group(function () {
        Route::post('reviews/{review}/reply', [ReviewController::class, 'reply'])->whereNumber('review')->name('reviews.reply');
        Route::post('reviews/{review}/toggle', [ReviewController::class, 'toggle'])->whereNumber('review')->name('reviews.toggle');

        Route::prefix('marketing')->group(function () {
            Route::get('loyalty', [MarketingSettingsController::class, 'edit'])->name('marketing.settings');
            Route::put('loyalty', [MarketingSettingsController::class, 'update'])->name('marketing.settings.update');

            Route::get('promos', [PromoController::class, 'index'])->name('promos.index');
            Route::get('promos/create', [PromoController::class, 'create'])->name('promos.create');
            Route::post('promos', [PromoController::class, 'store'])->name('promos.store');
            Route::get('promos/{promo}/edit', [PromoController::class, 'edit'])->whereNumber('promo')->name('promos.edit');
            Route::put('promos/{promo}', [PromoController::class, 'update'])->whereNumber('promo')->name('promos.update');
            Route::post('promos/{promo}/toggle', [PromoController::class, 'toggle'])->whereNumber('promo')->name('promos.toggle');
            Route::delete('promos/{promo}', [PromoController::class, 'destroy'])->whereNumber('promo')->name('promos.destroy');

            Route::get('pricing', [PriceRuleController::class, 'index'])->name('pricing.index');
            Route::post('pricing', [PriceRuleController::class, 'store'])->name('pricing.store');
            Route::post('pricing/{rule}/toggle', [PriceRuleController::class, 'toggle'])->whereNumber('rule')->name('pricing.toggle');
            Route::delete('pricing/{rule}', [PriceRuleController::class, 'destroy'])->whereNumber('rule')->name('pricing.destroy');

            Route::get('segments', [SegmentController::class, 'index'])->name('segments.index');
            Route::post('segments', [SegmentController::class, 'store'])->name('segments.store');
            Route::delete('segments/{segment}', [SegmentController::class, 'destroy'])->whereNumber('segment')->name('segments.destroy');

            Route::get('gift-cards', [GiftCardController::class, 'index'])->name('gifts.index');
            Route::post('gift-cards', [GiftCardController::class, 'store'])->middleware('throttle:30,1')->name('gifts.store');
            Route::post('gift-cards/{card}/toggle', [GiftCardController::class, 'toggle'])->whereNumber('card')->name('gifts.toggle');

            Route::get('flyer', [GrowthToolsController::class, 'flyer'])->name('marketing.flyer');
            Route::get('widget', [GrowthToolsController::class, 'widget'])->name('marketing.widget');

            Route::get('banners', [BannerController::class, 'index'])->name('banners.index');
            Route::get('banners/create', [BannerController::class, 'create'])->name('banners.create');
            Route::post('banners', [BannerController::class, 'store'])->name('banners.store');
            Route::get('banners/{banner}/edit', [BannerController::class, 'edit'])->whereNumber('banner')->name('banners.edit');
            Route::put('banners/{banner}', [BannerController::class, 'update'])->whereNumber('banner')->name('banners.update');
            Route::delete('banners/{banner}', [BannerController::class, 'destroy'])->whereNumber('banner')->name('banners.destroy');

            Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
            Route::get('campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
            Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
            Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->whereNumber('campaign')->name('campaigns.show');
            Route::get('campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->whereNumber('campaign')->name('campaigns.edit');
            Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->whereNumber('campaign')->name('campaigns.update');
            Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->whereNumber('campaign')->name('campaigns.destroy');
            Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send'])->middleware('throttle:5,1')->whereNumber('campaign')->name('campaigns.send');
            Route::post('campaigns/{campaign}/test', [CampaignController::class, 'test'])->middleware('throttle:10,1')->whereNumber('campaign')->name('campaigns.test');
        });
    });
});
