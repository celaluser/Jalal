<?php

use App\Modules\Auth\Http\Controllers\EmailVerificationController;
use App\Modules\Auth\Http\Controllers\GoogleController;
use App\Modules\Auth\Http\Controllers\LoginController;
use App\Modules\Auth\Http\Controllers\PasswordResetController;
use App\Modules\Auth\Http\Controllers\RegisterController;
use App\Modules\Auth\Http\Controllers\SocialController;
use App\Modules\Auth\Http\Controllers\TwoFactorController;
use App\Modules\Core\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class])->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->middleware(['throttle:login', 'recaptcha'])->name('login.store');

        Route::get('/register', [RegisterController::class, 'create'])->name('register');
        Route::post('/register', [RegisterController::class, 'store'])->middleware(['throttle:register', 'recaptcha']);

        Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware(['throttle:login', 'recaptcha'])->name('password.email');
        Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');

        Route::get('/two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
        Route::post('/two-factor-challenge', [TwoFactorController::class, 'verify'])->middleware('throttle:login')->name('two-factor.verify');

        Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
        Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');

        Route::get('/auth/social/{provider}', [SocialController::class, 'redirect'])->whereIn('provider', ['facebook', 'apple'])->name('social.redirect');
        // Apple posts the result back from its own site, so this one cannot carry a CSRF token (its state is signed instead).
        Route::match(['get', 'post'], '/auth/social/{provider}/callback', [SocialController::class, 'callback'])->whereIn('provider', ['facebook', 'apple'])->name('social.callback');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('signed')->name('verification.verify');
        Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
            ->middleware('throttle:6,1')->name('verification.send');

        Route::get('/profile/two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
        Route::post('/profile/two-factor', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
        Route::delete('/profile/two-factor', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
    });
});
