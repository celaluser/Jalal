<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Tables\Http\Controllers\AreaController;
use App\Modules\Tables\Http\Controllers\QrController;
use App\Modules\Tables\Http\Controllers\TableController;
use Illuminate\Support\Facades\Route;

// Viewing needs tables.view or tables.manage; every change needs tables.manage.
Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user'])->prefix('tables')->name('tables.')->group(function () {
    Route::middleware('permission:tables.manage|tables.view')->group(function () {
        Route::get('/', [TableController::class, 'index'])->name('index');
        Route::get('qr', [QrController::class, 'show'])->name('qr');
        Route::get('qr/preview', [QrController::class, 'preview'])->middleware('throttle:60,1')->name('qr.preview');
        Route::get('qr/download/{target}/{format}', [QrController::class, 'download'])->where('format', 'png|svg')->middleware('throttle:60,1')->name('qr.download');
        Route::get('qr/zip/{format}', [QrController::class, 'zip'])->where('format', 'png|svg')->middleware('throttle:10,1')->name('qr.zip');
        Route::get('qr/pdf', [QrController::class, 'pdf'])->middleware('throttle:10,1')->name('qr.pdf');
    });

    Route::middleware('permission:tables.manage')->group(function () {
        Route::post('/', [TableController::class, 'store'])->name('store');
        Route::post('bulk', [TableController::class, 'bulk'])->name('bulk');
        Route::put('qr', [QrController::class, 'update'])->name('qr.update');
        Route::get('{table}/edit', [TableController::class, 'edit'])->whereNumber('table')->name('edit');
        Route::put('{table}', [TableController::class, 'update'])->whereNumber('table')->name('update');
        Route::delete('{table}', [TableController::class, 'destroy'])->whereNumber('table')->name('destroy');
        Route::post('{table}/regenerate', [TableController::class, 'regenerate'])->whereNumber('table')->name('regenerate');

        Route::post('areas', [AreaController::class, 'store'])->name('areas.store');
        Route::put('areas/{area}', [AreaController::class, 'update'])->whereNumber('area')->name('areas.update');
        Route::delete('areas/{area}', [AreaController::class, 'destroy'])->whereNumber('area')->name('areas.destroy');
    });
});
