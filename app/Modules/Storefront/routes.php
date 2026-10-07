<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Storefront\Http\Controllers\PublicMenuController;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

// The menu works at /r/{slug} everywhere, and on a restaurant's own (sub)domain. The domain's root
// page is served by the landing route (it hands over to this controller); the rest is below.
// On the platform's own domain the host routes resolve nothing and answer 404.
Route::middleware(['web', SetLocale::class, ResolveTenant::class])
    ->prefix(config('tenancy.path_prefix').'/{restaurant}')->name('storefront.')->where(['restaurant' => '[A-Za-z0-9-]+'])->group(function () {
        Route::get('/', [PublicMenuController::class, 'show'])->name('menu');
        Route::get('t/{token}', [PublicMenuController::class, 'table'])->where('token', '[a-z0-9]{6,32}')->name('table');
        Route::post('cart/quote', [PublicMenuController::class, 'quote'])->middleware('throttle:60,1')->name('quote');
    });

Route::middleware(['web', SetLocale::class, ResolveTenant::class])->name('storefront.host.')->group(function () {
    Route::get('t/{token}', [PublicMenuController::class, 'table'])->where('token', '[a-z0-9]{6,32}')->name('table');
    Route::post('cart/quote', [PublicMenuController::class, 'quote'])->middleware('throttle:60,1')->name('quote');
});
