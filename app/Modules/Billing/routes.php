<?php

use App\Modules\Billing\Http\Controllers\PaymentCallbackController;
use App\Modules\Core\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// No session and no CSRF: the caller is the payment gateway, which authenticates itself per request.
Route::post('/webhooks/payments/{gateway}', [PaymentCallbackController::class, 'webhook'])
    ->middleware('throttle:120,1')->name('webhooks.payments');

// Customer return from a hosted payment page (iyzico posts back, hence match). CSRF-exempt in bootstrap/app.php.
Route::match(['get', 'post'], '/billing/return/{gateway}/{invoice}', [PaymentCallbackController::class, 'return'])
    ->middleware(['web', SetLocale::class, 'throttle:60,1'])->name('billing.return');
