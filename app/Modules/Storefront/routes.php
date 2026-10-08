<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Storefront\Http\Controllers\AccountController;
use App\Modules\Storefront\Http\Controllers\PublicMenuController;
use App\Modules\Storefront\Http\Controllers\PwaController;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

// The menu works at /r/{slug} everywhere, and on a restaurant's own (sub)domain. The domain's root
// page is served by the landing route (it hands over to this controller); the rest is below.
// On the platform's own domain the host routes resolve nothing and answer 404.
// Installable app files: manifest, icons and the service worker.
$pwa = function () {
    Route::get('manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('pwa-icon-{size}.png', [PwaController::class, 'icon'])->whereNumber('size')->name('pwa.icon');
    Route::get('sw.js', [PwaController::class, 'worker'])->name('pwa.worker');

    // Optional guest account (e-mail sign-in link, no password).
    Route::get('account', [AccountController::class, 'show'])->name('account');
    Route::post('account/login', [AccountController::class, 'sendLink'])->middleware('throttle:5,1')->name('account.login');
    Route::get('account/verify/{customer}', [AccountController::class, 'verify'])->whereNumber('customer')->middleware('throttle:20,1')->name('account.verify');
    Route::put('account', [AccountController::class, 'update'])->name('account.update');
    Route::post('account/logout', [AccountController::class, 'logout'])->name('account.logout');
    Route::delete('account', [AccountController::class, 'destroy'])->middleware('throttle:5,1')->name('account.destroy');
};

Route::middleware(['web', SetLocale::class, ResolveTenant::class])
    ->prefix(config('tenancy.path_prefix').'/{restaurant}')->name('storefront.')->where(['restaurant' => '[A-Za-z0-9-]+'])->group(function () use ($pwa) {
        Route::get('/', [PublicMenuController::class, 'show'])->name('menu');
        Route::get('t/{token}', [PublicMenuController::class, 'table'])->where('token', '[a-z0-9]{6,32}')->name('table');
        Route::post('cart/quote', [PublicMenuController::class, 'quote'])->middleware('throttle:60,1')->name('quote');
        $pwa();
    });

Route::middleware(['web', SetLocale::class, ResolveTenant::class])->name('storefront.host.')->group(function () use ($pwa) {
    Route::get('t/{token}', [PublicMenuController::class, 'table'])->where('token', '[a-z0-9]{6,32}')->name('table');
    Route::post('cart/quote', [PublicMenuController::class, 'quote'])->middleware('throttle:60,1')->name('quote');
    $pwa();
});
