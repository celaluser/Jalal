<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Store\Http\Controllers\AdminStoreController;
use App\Modules\Store\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

$slug = '(feature|theme):[a-z0-9_]+';

// The restaurant's store. Anyone on staff may look; buying needs the billing permission.
Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user'])->prefix('store')->name('store.')->group(function () use ($slug) {
    Route::get('/', [StoreController::class, 'index'])->name('index');
    Route::get('{slug}', [StoreController::class, 'show'])->where('slug', $slug)->name('show');

    Route::middleware('permission:billing.manage')->group(function () use ($slug) {
        Route::post('{slug}/buy', [StoreController::class, 'buy'])->where('slug', $slug)->middleware('throttle:10,1')->name('buy');
        Route::post('{slug}/trial', [StoreController::class, 'trial'])->where('slug', $slug)->middleware('throttle:10,1')->name('trial');
    });
});

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:platform.manage'])->prefix('admin/store')->name('admin.store.')->group(function () use ($slug) {
    Route::get('/', [AdminStoreController::class, 'index'])->name('index');
    Route::get('sales', [AdminStoreController::class, 'sales'])->name('sales');
    Route::post('grant', [AdminStoreController::class, 'grant'])->name('grant');
    Route::delete('entitlements/{entitlement}', [AdminStoreController::class, 'revoke'])->whereNumber('entitlement')->name('revoke');
    Route::get('{slug}/edit', [AdminStoreController::class, 'edit'])->where('slug', $slug)->name('edit');
    Route::put('{slug}', [AdminStoreController::class, 'update'])->where('slug', $slug)->name('update');
    Route::delete('{slug}', [AdminStoreController::class, 'reset'])->where('slug', $slug)->name('reset');
});
