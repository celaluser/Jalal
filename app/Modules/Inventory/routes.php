<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:menu.manage'])->prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [InventoryController::class, 'index'])->name('index');
    Route::post('ingredients', [InventoryController::class, 'store'])->name('store');
    Route::put('ingredients/{ingredient}', [InventoryController::class, 'update'])->whereNumber('ingredient')->name('update');
    Route::delete('ingredients/{ingredient}', [InventoryController::class, 'destroy'])->whereNumber('ingredient')->name('destroy');
    Route::post('purchases', [InventoryController::class, 'purchase'])->middleware('throttle:60,1')->name('purchase');
    Route::post('suppliers', [InventoryController::class, 'storeSupplier'])->name('suppliers.store');
    Route::delete('suppliers/{supplier}', [InventoryController::class, 'destroySupplier'])->whereNumber('supplier')->name('suppliers.destroy');

    Route::get('recipes', [InventoryController::class, 'recipes'])->name('recipes');
    Route::get('recipes/{product}', [InventoryController::class, 'editRecipe'])->whereNumber('product')->name('recipe');
    Route::put('recipes/{product}', [InventoryController::class, 'updateRecipe'])->whereNumber('product')->name('recipe.update');
});
