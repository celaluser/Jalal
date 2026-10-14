<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Updater\Http\Controllers\UpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:platform.manage'])
    ->prefix('admin/updates')->name('admin.updates.')->group(function () {
        Route::get('/', [UpdateController::class, 'index'])->name('index');
        Route::post('/', [UpdateController::class, 'upload'])->middleware('throttle:10,1')->name('upload');
        Route::get('/review', [UpdateController::class, 'review'])->name('review');
        Route::post('/apply', [UpdateController::class, 'apply'])->middleware('throttle:5,1')->name('apply');
        Route::delete('/pending', [UpdateController::class, 'cancel'])->name('cancel');
    });
