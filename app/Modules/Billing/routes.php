<?php

use App\Modules\Billing\Http\Controllers\PaymentCallbackController;
use App\Modules\Billing\Http\Controllers\SubscriptionPanelController;
use App\Modules\Core\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// No session and no CSRF: the caller is the payment gateway, which authenticates itself per request.
Route::post('/webhooks/payments/{gateway}', [PaymentCallbackController::class, 'webhook'])
    ->middleware('throttle:120,1')->name('webhooks.payments');

// Customer return from a hosted payment page (iyzico posts back, hence match). CSRF-exempt in bootstrap/app.php.
Route::match(['get', 'post'], '/billing/return/{gateway}/{invoice}', [PaymentCallbackController::class, 'return'])
    ->middleware(['web', SetLocale::class, 'throttle:60,1'])->name('billing.return');

// Restaurant owner: subscription, checkout and invoices.
Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:billing.manage'])
    ->prefix('subscription')->name('billing.')->group(function () {
        Route::get('/', [SubscriptionPanelController::class, 'index'])->name('index');
        Route::post('select/{plan}', [SubscriptionPanelController::class, 'select'])->name('select');
        Route::get('checkout/{plan}', [SubscriptionPanelController::class, 'checkout'])->name('checkout');
        Route::get('checkout/{plan}/preview', [SubscriptionPanelController::class, 'preview'])->middleware('throttle:30,1')->name('preview');
        Route::post('checkout/{plan}', [SubscriptionPanelController::class, 'start'])->middleware('throttle:10,1')->name('start');
        Route::post('cancel', [SubscriptionPanelController::class, 'cancel'])->name('cancel');
        Route::get('invoices/{invoice}/pdf', [SubscriptionPanelController::class, 'pdf'])->whereNumber('invoice')->name('invoice.pdf');
    });
