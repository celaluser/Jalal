<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class])->group(function () {
    // The public landing page is built in Phase 3 (CMS); until then send visitors to the login.
    Route::get('/', fn () => redirect()->route('login'));

    Route::middleware(['auth', 'verified', 'tenant.user'])->group(function () {
        Route::view('/dashboard', 'dashboard')->name('dashboard');
    });
});
