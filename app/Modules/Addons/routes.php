<?php

use App\Modules\Addons\Http\Controllers\AddonController;
use App\Modules\Core\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:platform.manage'])
    ->prefix('admin/addons')->name('admin.addons.')->group(function () {
        Route::get('/', [AddonController::class, 'index'])->name('index');
        Route::post('/', [AddonController::class, 'upload'])->middleware('throttle:10,1')->name('upload');
        Route::post('{slug}/toggle', [AddonController::class, 'toggle'])->middleware('throttle:20,1')->name('toggle');
        Route::delete('{slug}', [AddonController::class, 'destroy'])->middleware('throttle:10,1')->name('destroy');
    });
