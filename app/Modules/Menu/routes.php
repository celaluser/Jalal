<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Menu\Http\Controllers\AppearanceController;
use App\Modules\Menu\Http\Controllers\CategoryController;
use App\Modules\Menu\Http\Controllers\MenuController;
use App\Modules\Menu\Http\Controllers\OptionGroupController;
use App\Modules\Menu\Http\Controllers\ProductController;
use App\Modules\Menu\Http\Controllers\ReorderController;
use App\Modules\Menu\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:menu.manage'])
    ->prefix('menu')->name('menu.')->group(function () {
        Route::get('/', [MenuController::class, 'index'])->name('index');

        Route::resource('categories', CategoryController::class)->except(['index', 'show']);
        Route::resource('products', ProductController::class)->except(['index', 'show']);
        Route::post('products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');
        Route::post('products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');

        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
        Route::put('stock', [StockController::class, 'update'])->name('stock.update');
        Route::post('stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
        Route::get('export', [StockController::class, 'export'])->middleware('throttle:10,1')->name('export');

        Route::resource('option-groups', OptionGroupController::class)->except('show');

        Route::post('reorder/{type}', ReorderController::class)->where('type', 'categories|products|options|option-groups')->name('reorder');
    });

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:settings.manage'])->group(function () {
    Route::get('/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::put('/appearance', [AppearanceController::class, 'update'])->name('appearance.update');
});
