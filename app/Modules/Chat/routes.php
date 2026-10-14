<?php

use App\Modules\Chat\Http\Controllers\GuestChatController;
use App\Modules\Chat\Http\Controllers\StaffChatController;
use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

$guest = function () {
    Route::get('order/{token}/chat', [GuestChatController::class, 'messages'])->where('token', '[a-z0-9]{24}')->middleware('throttle:60,1')->name('order.chat');
    Route::post('order/{token}/chat', [GuestChatController::class, 'send'])->where('token', '[a-z0-9]{24}')->middleware('throttle:15,1')->name('order.chat.send');
};

Route::middleware(['web', SetLocale::class, ResolveTenant::class])->prefix(config('tenancy.path_prefix').'/{restaurant}')->name('storefront.')->where(['restaurant' => '[A-Za-z0-9-]+'])->group($guest);
Route::middleware(['web', SetLocale::class, ResolveTenant::class])->name('storefront.host.')->group($guest);

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:orders.view'])->prefix('chat')->name('chat.')->group(function () {
    Route::get('/', [StaffChatController::class, 'index'])->name('index');
    Route::post('toggle', [StaffChatController::class, 'toggle'])->middleware('permission:settings.manage')->name('toggle');
    Route::get('{order}', [StaffChatController::class, 'show'])->whereNumber('order')->name('show');
    Route::get('{order}/messages', [StaffChatController::class, 'messages'])->whereNumber('order')->middleware('throttle:120,1')->name('messages');
    Route::post('{order}/reply', [StaffChatController::class, 'reply'])->whereNumber('order')->middleware('throttle:60,1')->name('reply');
});
