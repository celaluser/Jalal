<?php

use App\Modules\Analytics\Http\Controllers\ReportController;
use App\Modules\Core\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:reports.view'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('export', [ReportController::class, 'export'])->middleware('throttle:10,1')->name('export');
});
