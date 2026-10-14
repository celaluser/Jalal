<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Hub\Http\Controllers\ChannelController;
use App\Modules\Hub\Http\Controllers\HubOrderController;
use Illuminate\Support\Facades\Route;

// Orders arrive here from other platforms: no session, no CSRF, the secret in the address is the key.
Route::post('hub/{token}', [HubOrderController::class, 'store'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:120,1')->name('hub.receive');

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:api.manage'])->prefix('channels')->name('channels.')->group(function () {
    Route::get('/', [ChannelController::class, 'index'])->name('index');
    Route::post('/', [ChannelController::class, 'store'])->middleware('throttle:20,1')->name('store');
    Route::post('{channel}/renew', [ChannelController::class, 'renew'])->whereNumber('channel')->middleware('throttle:20,1')->name('renew');
    Route::post('{channel}/toggle', [ChannelController::class, 'toggle'])->whereNumber('channel')->name('toggle');
    Route::delete('{channel}', [ChannelController::class, 'destroy'])->whereNumber('channel')->name('destroy');
});
