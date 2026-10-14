<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Messaging\Http\Controllers\MessagingSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:platform.manage'])->prefix('admin/messaging')->name('admin.messaging.')->group(function () {
    Route::get('/', [MessagingSettingsController::class, 'edit'])->name('edit');
    Route::put('choose', [MessagingSettingsController::class, 'choose'])->name('choose');
    Route::put('providers/{provider}', [MessagingSettingsController::class, 'update'])->name('update');
    Route::post('test', [MessagingSettingsController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
