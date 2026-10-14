<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Team\Http\Controllers\RoleController;
use App\Modules\Team\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user'])->prefix('team')->name('team.')->group(function () {
    Route::get('/', [TeamController::class, 'index'])->middleware('permission:staff.view|staff.manage')->name('index');

    Route::middleware('permission:staff.manage')->group(function () {
        Route::get('invite', [TeamController::class, 'create'])->name('create');
        Route::post('/', [TeamController::class, 'store'])->middleware('throttle:20,60')->name('store');
        Route::get('{user}/edit', [TeamController::class, 'edit'])->whereNumber('user')->name('edit');
        Route::put('{user}', [TeamController::class, 'update'])->whereNumber('user')->name('update');
        Route::post('{user}/toggle', [TeamController::class, 'toggle'])->whereNumber('user')->name('toggle');
        Route::post('{user}/resend', [TeamController::class, 'resend'])->middleware('throttle:10,60')->whereNumber('user')->name('resend');
        Route::delete('{user}', [TeamController::class, 'destroy'])->whereNumber('user')->name('destroy');
    });

    Route::middleware('permission:roles.manage')->prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('create', [RoleController::class, 'create'])->name('create');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::get('{role}/edit', [RoleController::class, 'edit'])->whereNumber('role')->name('edit');
        Route::put('{role}', [RoleController::class, 'update'])->whereNumber('role')->name('update');
        Route::delete('{role}', [RoleController::class, 'destroy'])->whereNumber('role')->name('destroy');
    });
});
