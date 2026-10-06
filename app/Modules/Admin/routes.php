<?php

use App\Modules\Admin\Http\Controllers\BillingSettingsController;
use App\Modules\Admin\Http\Controllers\CouponController;
use App\Modules\Admin\Http\Controllers\DashboardController;
use App\Modules\Admin\Http\Controllers\EmailTemplateController;
use App\Modules\Admin\Http\Controllers\ImpersonationController;
use App\Modules\Admin\Http\Controllers\InvoiceController;
use App\Modules\Admin\Http\Controllers\PaymentSettingsController;
use App\Modules\Admin\Http\Controllers\PlanController;
use App\Modules\Admin\Http\Controllers\RestaurantController;
use App\Modules\Admin\Http\Controllers\SettingsController;
use App\Modules\Admin\Http\Controllers\SubscriptionController;
use App\Modules\Core\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// Ending an impersonation must work while logged in as the impersonated owner, so it only needs `auth`.
Route::middleware(['web', SetLocale::class, 'auth'])->post('/impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonation.stop');

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:platform.manage'])
    ->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
        Route::get('restaurants/{restaurant}', [RestaurantController::class, 'show'])->name('restaurants.show');
        Route::put('restaurants/{restaurant}', [RestaurantController::class, 'update'])->name('restaurants.update');
        Route::post('restaurants/{restaurant}/suspend', [RestaurantController::class, 'suspend'])->name('restaurants.suspend');
        Route::post('restaurants/{restaurant}/unsuspend', [RestaurantController::class, 'unsuspend'])->name('restaurants.unsuspend');
        Route::delete('restaurants/{restaurant}', [RestaurantController::class, 'destroy'])->name('restaurants.destroy');
        Route::post('restaurants/{id}/restore', [RestaurantController::class, 'restore'])->name('restaurants.restore');
        Route::post('restaurants/{restaurant}/impersonate', [ImpersonationController::class, 'start'])->name('restaurants.impersonate');

        Route::resource('plans', PlanController::class)->except('show');

        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::post('subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew'])->name('subscriptions.renew');
        Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/paid', [InvoiceController::class, 'markPaid'])->name('invoices.paid');
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');

        Route::resource('coupons', CouponController::class)->except('show');

        Route::get('settings/billing', [BillingSettingsController::class, 'edit'])->name('settings.billing');
        Route::put('settings/billing', [BillingSettingsController::class, 'update'])->name('settings.billing.update');

        Route::get('settings/{section}', [SettingsController::class, 'edit'])->name('settings.section')
            ->where('section', 'general|seo|security|auth|mail|domains|ai|realtime|storage');
        Route::put('settings/{section}', [SettingsController::class, 'update'])->name('settings.section.update')
            ->where('section', 'general|seo|security|auth|mail|domains|ai|realtime|storage');
        Route::post('settings/mail/test', [SettingsController::class, 'testMail'])->middleware('throttle:5,1')->name('settings.mail.test');

        Route::get('email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::get('email-templates/{key}', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
        Route::put('email-templates/{key}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
        Route::delete('email-templates/{key}', [EmailTemplateController::class, 'reset'])->name('email-templates.reset');
        Route::post('email-templates/{key}/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');
        Route::post('email-templates/{key}/test', [EmailTemplateController::class, 'test'])->middleware('throttle:5,1')->name('email-templates.test');

        Route::get('settings/payments', [PaymentSettingsController::class, 'edit'])->name('settings.payments');
        Route::put('settings/payments/{gateway}', [PaymentSettingsController::class, 'update'])->name('settings.payments.update');
    });
