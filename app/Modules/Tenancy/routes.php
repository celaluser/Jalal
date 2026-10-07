<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Tenancy\Http\Controllers\OnboardingController;
use App\Modules\Tenancy\Http\Controllers\RestaurantSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:settings.manage'])->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding/profile', [OnboardingController::class, 'profile'])->name('onboarding.profile');
    Route::post('/onboarding/branding', [OnboardingController::class, 'branding'])->middleware('throttle:20,1')->name('onboarding.branding');
    Route::post('/onboarding/finish', [OnboardingController::class, 'finish'])->name('onboarding.finish');

    Route::get('/settings/restaurant', [RestaurantSettingsController::class, 'edit'])->name('restaurant.settings');
    Route::put('/settings/restaurant/profile', [RestaurantSettingsController::class, 'updateProfile'])->name('restaurant.settings.profile');
    Route::post('/settings/restaurant/branding', [RestaurantSettingsController::class, 'updateBranding'])->middleware('throttle:20,1')->name('restaurant.settings.branding');
});
