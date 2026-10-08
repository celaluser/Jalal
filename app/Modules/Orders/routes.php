<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Orders\Http\Controllers\CustomerOrderController;
use App\Modules\Orders\Http\Controllers\DeliveryController;
use App\Modules\Orders\Http\Controllers\GuestServiceController;
use App\Modules\Orders\Http\Controllers\OrderBoardController;
use App\Modules\Orders\Http\Controllers\OrderHistoryController;
use App\Modules\Orders\Http\Controllers\OrderPaymentController;
use App\Modules\Orders\Http\Controllers\OrderSettingsController;
use App\Modules\Orders\Http\Controllers\PaymentSettingsController;
use App\Modules\Orders\Http\Controllers\PosController;
use App\Modules\Orders\Http\Controllers\PrintQueueController;
use App\Modules\Orders\Http\Controllers\ReceiptController;
use App\Modules\Orders\Http\Controllers\ShiftController;
use App\Modules\Orders\Http\Controllers\StaffAppController;
use App\Modules\Orders\Http\Controllers\TabletModeController;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

$guest = function () {
    Route::post('order', [CustomerOrderController::class, 'store'])->middleware('throttle:8,1')->name('order.store');
    Route::get('order/{token}', [CustomerOrderController::class, 'show'])->where('token', '[a-z0-9]{24}')->name('order.show');
    Route::get('order/{token}/status', [CustomerOrderController::class, 'status'])->where('token', '[a-z0-9]{24}')->middleware('throttle:120,1')->name('order.status');
    Route::post('request', [GuestServiceController::class, 'request'])->middleware('throttle:6,1')->name('request');
    Route::get('tab', [GuestServiceController::class, 'tab'])->middleware('throttle:30,1')->name('tab');
    Route::post('order/{token}/push', [GuestServiceController::class, 'subscribePush'])->where('token', '[a-z0-9]{24}')->middleware('throttle:10,1')->name('order.push');
    Route::get('order/{token}/reorder', [GuestServiceController::class, 'reorder'])->where('token', '[a-z0-9]{24}')->middleware('throttle:30,1')->name('order.reorder');
    Route::post('order/{token}/pay', [OrderPaymentController::class, 'start'])->where('token', '[a-z0-9]{24}')->middleware('throttle:10,1')->name('order.pay');
    Route::match(['get', 'post'], 'order/{token}/pay/return', [OrderPaymentController::class, 'return'])->where('token', '[a-z0-9]{24}')->middleware('throttle:30,1')->name('order.pay.return');
    Route::post('pay/{gateway}/webhook', [OrderPaymentController::class, 'webhook'])->where('gateway', '[a-z_]+')->middleware('throttle:120,1')->name('pay.webhook');
    Route::get('order/{token}/receipt', [ReceiptController::class, 'show'])->where('token', '[a-z0-9]{24}')->middleware('throttle:30,1')->name('order.receipt');
    Route::get('order/{token}/receipt.pdf', [ReceiptController::class, 'pdf'])->where('token', '[a-z0-9]{24}')->middleware('throttle:10,1')->name('order.receipt.pdf');
    Route::post('order/{token}/cancel', [CustomerOrderController::class, 'cancel'])->where('token', '[a-z0-9]{24}')->middleware('throttle:10,1')->name('order.cancel');
};

// Guest side: on /r/{slug} and on a restaurant's own domain.
Route::middleware(['web', SetLocale::class, ResolveTenant::class])
    ->prefix(config('tenancy.path_prefix').'/{restaurant}')->name('storefront.')->where(['restaurant' => '[A-Za-z0-9-]+'])->group($guest);
Route::middleware(['web', SetLocale::class, ResolveTenant::class])->name('storefront.host.')->group($guest);

// Printer bridge: no session, the secret token in the address is the credential.
Route::prefix('print/{token}')->middleware('throttle:240,1')->name('print.')->where(['token' => '[0-9]+-[a-z0-9]{32}'])->group(function () {
    Route::get('next', [PrintQueueController::class, 'next'])->name('next');
    Route::post('{job}/done', [PrintQueueController::class, 'done'])->whereNumber('job')->name('done');
});

// Staff side. Viewing needs orders.view (waiters, cashiers) or kitchen.view; what each person may change is decided per order.
Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user'])->group(function () {
    Route::post('tablet-mode', TabletModeController::class)->name('tablet.toggle');
    Route::get('staff.webmanifest', [StaffAppController::class, 'manifest'])->name('staff.manifest');
    Route::get('staff-icon-{size}.png', [StaffAppController::class, 'icon'])->whereNumber('size')->name('staff.icon');

    // History has customer details, so it is for people who manage orders.
    Route::middleware('permission:orders.manage')->prefix('orders')->name('orders.')->group(function () {
        Route::get('history', [OrderHistoryController::class, 'index'])->name('history');
        Route::get('history.csv', [OrderHistoryController::class, 'export'])->middleware('throttle:10,1')->name('history.export');
    });

    Route::middleware('permission:orders.view|kitchen.view')->prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderBoardController::class, 'index'])->name('board');
        Route::get('kitchen-display', [OrderBoardController::class, 'kds'])->name('kds');
        Route::post('{order}/station-done', [OrderBoardController::class, 'stationDone'])->whereNumber('order')->name('station-done');
        Route::get('batch', [OrderBoardController::class, 'batch'])->name('batch');
        Route::post('requests/{serviceRequest}/done', [OrderBoardController::class, 'requestDone'])->whereNumber('serviceRequest')->name('requests.done');
        Route::get('feed', [OrderBoardController::class, 'feed'])->middleware('throttle:60,1')->name('feed');
        Route::get('{order}', [OrderBoardController::class, 'show'])->whereNumber('order')->name('show');
        Route::get('{order}/ticket', [OrderBoardController::class, 'ticket'])->whereNumber('order')->name('ticket');
        Route::post('{order}/status', [OrderBoardController::class, 'transition'])->whereNumber('order')->name('status');
        Route::post('{order}/print', [PrintQueueController::class, 'queue'])->whereNumber('order')->name('print');
        Route::post('{order}/dispatch', [OrderBoardController::class, 'dispatch'])->whereNumber('order')->name('dispatch');
        Route::post('{order}/discount', [OrderBoardController::class, 'discount'])->whereNumber('order')->name('discount');
        Route::post('tables/{table}/close', [OrderBoardController::class, 'closeTable'])->whereNumber('table')->name('tables.close');
        Route::post('{order}/payments', [OrderBoardController::class, 'addPayment'])->whereNumber('order')->name('payments.add');
        Route::post('payments/{payment}/refund', [OrderBoardController::class, 'refund'])->whereNumber('payment')->name('payments.refund');
        Route::post('{order}/pay', [OrderBoardController::class, 'pay'])->whereNumber('order')->name('pay');
    });

    // Staff order entry for waiters and cashiers.
    Route::middleware('permission:orders.create|orders.manage')->prefix('orders')->name('orders.pos.')->group(function () {
        Route::get('new', [PosController::class, 'index'])->name('index');
        Route::post('new', [PosController::class, 'store'])->middleware('throttle:60,1')->name('store');
    });

    // Delivery: zones for the owner, assigning couriers for managers, and the courier's own list.
    Route::middleware('permission:delivery.manage')->prefix('delivery')->name('delivery.')->group(function () {
        Route::get('zones', [DeliveryController::class, 'zones'])->name('zones');
        Route::post('zones', [DeliveryController::class, 'storeZone'])->name('zones.store');
        Route::put('zones/{zone}', [DeliveryController::class, 'updateZone'])->whereNumber('zone')->name('zones.update');
        Route::delete('zones/{zone}', [DeliveryController::class, 'destroyZone'])->whereNumber('zone')->name('zones.destroy');
    });
    Route::post('orders/{order}/courier', [DeliveryController::class, 'assign'])->whereNumber('order')->name('orders.courier');
    Route::middleware('permission:delivery.view')->prefix('courier')->name('courier.')->group(function () {
        Route::get('/', [DeliveryController::class, 'mine'])->name('index');
        Route::post('{order}/claim', [DeliveryController::class, 'claim'])->whereNumber('order')->name('claim');
        Route::post('{order}/leave', [DeliveryController::class, 'leave'])->whereNumber('order')->name('leave');
        Route::post('{order}/delivered', [DeliveryController::class, 'delivered'])->whereNumber('order')->name('delivered');
    });

    Route::middleware('permission:payments.manage|orders.manage')->prefix('shifts')->name('shifts.')->group(function () {
        Route::get('/', [ShiftController::class, 'index'])->name('index');
        Route::post('/', [ShiftController::class, 'open'])->name('open');
        Route::post('{shift}/close', [ShiftController::class, 'close'])->whereNumber('shift')->name('close');
    });

    Route::post('orders/pause', [OrderBoardController::class, 'pause'])->middleware('permission:orders.manage')->name('orders.pause');

    Route::middleware('permission:payments.manage')->group(function () {
        Route::get('settings/payments', [PaymentSettingsController::class, 'edit'])->name('payments.settings');
        Route::put('settings/payments/{gateway}', [PaymentSettingsController::class, 'update'])->where('gateway', '[a-z_]+')->name('payments.settings.update');
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::post('settings/ordering/print-token', [PrintQueueController::class, 'renewToken'])->name('orders.settings.print-token');
        Route::get('settings/ordering', [OrderSettingsController::class, 'edit'])->name('orders.settings');
        Route::put('settings/ordering', [OrderSettingsController::class, 'update'])->name('orders.settings.update');
    });
});
