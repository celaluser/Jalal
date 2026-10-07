<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Orders\Http\Controllers\CustomerOrderController;
use App\Modules\Orders\Http\Controllers\OrderBoardController;
use App\Modules\Orders\Http\Controllers\OrderSettingsController;
use App\Modules\Orders\Http\Controllers\PosController;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

$guest = function () {
    Route::post('order', [CustomerOrderController::class, 'store'])->middleware('throttle:8,1')->name('order.store');
    Route::get('order/{token}', [CustomerOrderController::class, 'show'])->where('token', '[a-z0-9]{24}')->name('order.show');
    Route::get('order/{token}/status', [CustomerOrderController::class, 'status'])->where('token', '[a-z0-9]{24}')->middleware('throttle:120,1')->name('order.status');
    Route::post('order/{token}/cancel', [CustomerOrderController::class, 'cancel'])->where('token', '[a-z0-9]{24}')->middleware('throttle:10,1')->name('order.cancel');
};

// Guest side: on /r/{slug} and on a restaurant's own domain.
Route::middleware(['web', SetLocale::class, ResolveTenant::class])
    ->prefix(config('tenancy.path_prefix').'/{restaurant}')->name('storefront.')->where(['restaurant' => '[A-Za-z0-9-]+'])->group($guest);
Route::middleware(['web', SetLocale::class, ResolveTenant::class])->name('storefront.host.')->group($guest);

// Staff side. Viewing needs orders.view (waiters, cashiers) or kitchen.view; what each person may change is decided per order.
Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user'])->group(function () {
    Route::middleware('permission:orders.view|kitchen.view')->prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderBoardController::class, 'index'])->name('board');
        Route::get('feed', [OrderBoardController::class, 'feed'])->middleware('throttle:60,1')->name('feed');
        Route::get('{order}', [OrderBoardController::class, 'show'])->whereNumber('order')->name('show');
        Route::get('{order}/ticket', [OrderBoardController::class, 'ticket'])->whereNumber('order')->name('ticket');
        Route::post('{order}/status', [OrderBoardController::class, 'transition'])->whereNumber('order')->name('status');
        Route::post('{order}/pay', [OrderBoardController::class, 'pay'])->whereNumber('order')->name('pay');
    });

    // Staff order entry for waiters and cashiers.
    Route::middleware('permission:orders.create|orders.manage')->prefix('orders')->name('orders.pos.')->group(function () {
        Route::get('new', [PosController::class, 'index'])->name('index');
        Route::post('new', [PosController::class, 'store'])->middleware('throttle:60,1')->name('store');
    });

    Route::post('orders/pause', [OrderBoardController::class, 'pause'])->middleware('permission:orders.manage')->name('orders.pause');

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('settings/ordering', [OrderSettingsController::class, 'edit'])->name('orders.settings');
        Route::put('settings/ordering', [OrderSettingsController::class, 'update'])->name('orders.settings.update');
    });
});
