<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Tenancy\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class])->group(function () {
    Route::middleware(['auth', 'verified', 'tenant.user'])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
    });
});
