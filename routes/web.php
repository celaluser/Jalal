<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class])->group(function () {
    Route::middleware(['auth', 'verified', 'tenant.user'])->group(function () {
        Route::view('/dashboard', 'dashboard')->name('dashboard');
    });
});
