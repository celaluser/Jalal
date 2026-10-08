<?php

use App\Modules\Branches\Http\Controllers\BranchController;
use App\Modules\Branches\Http\Controllers\BranchMenuController;
use App\Modules\Branches\Http\Controllers\BranchSwitchController;
use App\Modules\Core\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user'])->prefix('branches')->name('branches.')->group(function () {
    // Anyone on staff may change which branch they look at (the controller enforces fixed assignments).
    Route::post('switch', BranchSwitchController::class)->name('switch');

    Route::middleware('permission:branches.manage')->group(function () {
        Route::get('/', [BranchController::class, 'index'])->name('index');
        Route::get('create', [BranchController::class, 'create'])->name('create');
        Route::post('/', [BranchController::class, 'store'])->name('store');
        Route::get('{branch}/edit', [BranchController::class, 'edit'])->whereNumber('branch')->name('edit');
        Route::put('{branch}', [BranchController::class, 'update'])->whereNumber('branch')->name('update');
        Route::delete('{branch}', [BranchController::class, 'destroy'])->whereNumber('branch')->name('destroy');

        Route::get('{branch}/menu', [BranchMenuController::class, 'edit'])->whereNumber('branch')->name('menu');
        Route::put('{branch}/menu', [BranchMenuController::class, 'update'])->whereNumber('branch')->name('menu.update');
        Route::post('{branch}/menu/copy', [BranchMenuController::class, 'copy'])->whereNumber('branch')->name('menu.copy');
    });
});
