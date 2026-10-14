<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Support\Http\Controllers\SupportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:support.manage'])
    ->prefix('support')->name('support.')->group(function () {
        Route::get('/', [SupportController::class, 'index'])->name('index');
        Route::get('/create', [SupportController::class, 'create'])->name('create');
        Route::post('/', [SupportController::class, 'store'])->middleware('throttle:10,60')->name('store');
        Route::get('/{ticket}', [SupportController::class, 'show'])->whereNumber('ticket')->name('show');
        Route::post('/{ticket}/reply', [SupportController::class, 'reply'])->middleware('throttle:30,60')->whereNumber('ticket')->name('reply');
        Route::post('/{ticket}/close', [SupportController::class, 'close'])->whereNumber('ticket')->name('close');
    });
