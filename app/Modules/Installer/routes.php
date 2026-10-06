<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Installer\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class])->prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::get('/license', [InstallController::class, 'license'])->name('license');
    Route::post('/license', [InstallController::class, 'storeLicense'])->middleware('throttle:20,1');
    Route::get('/database', [InstallController::class, 'database'])->name('database');
    Route::post('/database', [InstallController::class, 'storeDatabase'])->middleware('throttle:20,1');
    Route::get('/admin', [InstallController::class, 'admin'])->name('admin');
    Route::post('/run', [InstallController::class, 'run'])->middleware('throttle:5,1')->name('run');
    Route::get('/finished', [InstallController::class, 'finished'])->name('finished');
});
