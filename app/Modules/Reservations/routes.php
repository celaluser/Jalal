<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Reservations\Http\Controllers\GuestReservationController;
use App\Modules\Reservations\Http\Controllers\ReservationController;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

$guest = function () {
    Route::get('reserve', [GuestReservationController::class, 'form'])->name('reserve');
    Route::get('reserve/slots', [GuestReservationController::class, 'slots'])->middleware('throttle:60,1')->name('reserve.slots');
    Route::post('reserve', [GuestReservationController::class, 'store'])->middleware('throttle:6,1')->name('reserve.store');
    Route::get('reserve/{token}', [GuestReservationController::class, 'show'])->where('token', '[a-z0-9]{24}')->name('reserve.show');
    Route::post('reserve/{token}/cancel', [GuestReservationController::class, 'cancel'])->where('token', '[a-z0-9]{24}')->middleware('throttle:10,1')->name('reserve.cancel');
};

Route::middleware(['web', SetLocale::class, ResolveTenant::class])->prefix(config('tenancy.path_prefix').'/{restaurant}')->name('storefront.')->where(['restaurant' => '[A-Za-z0-9-]+'])->group($guest);
Route::middleware(['web', SetLocale::class, ResolveTenant::class])->name('storefront.host.')->group($guest);

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user'])->prefix('reservations')->name('reservations.')->group(function () {
    Route::middleware('permission:reservations.manage|orders.create')->group(function () {
        Route::get('/', [ReservationController::class, 'index'])->name('index');
        Route::post('/', [ReservationController::class, 'store'])->name('store');
        Route::post('{reservation}/status', [ReservationController::class, 'status'])->whereNumber('reservation')->name('status');
        Route::post('{reservation}/table', [ReservationController::class, 'table'])->whereNumber('reservation')->name('table');
    });
    Route::middleware('permission:reservations.manage')->group(function () {
        Route::get('settings', [ReservationController::class, 'settings'])->name('settings');
        Route::put('settings', [ReservationController::class, 'saveSettings'])->name('settings.update');
    });
});
