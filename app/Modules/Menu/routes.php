<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Menu\Http\Controllers\CategoryController;
use App\Modules\Menu\Http\Controllers\MenuController;
use App\Modules\Menu\Http\Controllers\OptionGroupController;
use App\Modules\Menu\Http\Controllers\ProductController;
use App\Modules\Menu\Http\Controllers\ReorderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:menu.manage'])
    ->prefix('menu')->name('menu.')->group(function () {
        Route::get('/', [MenuController::class, 'index'])->name('index');

        Route::resource('categories', CategoryController::class)->except(['index', 'show']);
        Route::resource('products', ProductController::class)->except(['index', 'show']);
        Route::post('products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');
        Route::post('products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');

        Route::resource('option-groups', OptionGroupController::class)->except('show');

        Route::post('reorder/{type}', ReorderController::class)->where('type', 'categories|products|options|option-groups')->name('reorder');
    });
