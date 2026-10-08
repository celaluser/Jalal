<?php

use App\Modules\Admin\Http\Controllers\AnnouncementController;
use App\Modules\Admin\Http\Controllers\BackupController;
use App\Modules\Admin\Http\Controllers\BillingSettingsController;
use App\Modules\Admin\Http\Controllers\BlogPostController;
use App\Modules\Admin\Http\Controllers\CouponController;
use App\Modules\Admin\Http\Controllers\CurrencyController;
use App\Modules\Admin\Http\Controllers\DashboardController;
use App\Modules\Admin\Http\Controllers\EmailTemplateController;
use App\Modules\Admin\Http\Controllers\GuestPaymentsController;
use App\Modules\Admin\Http\Controllers\ImpersonationController;
use App\Modules\Admin\Http\Controllers\InvoiceController;
use App\Modules\Admin\Http\Controllers\LandingPageController;
use App\Modules\Admin\Http\Controllers\LanguageController;
use App\Modules\Admin\Http\Controllers\LogController;
use App\Modules\Admin\Http\Controllers\PageController;
use App\Modules\Admin\Http\Controllers\PaymentSettingsController;
use App\Modules\Admin\Http\Controllers\PlanController;
use App\Modules\Admin\Http\Controllers\RestaurantController;
use App\Modules\Admin\Http\Controllers\SettingsController;
use App\Modules\Admin\Http\Controllers\SubscriptionController;
use App\Modules\Admin\Http\Controllers\CronController;
use App\Modules\Admin\Http\Controllers\SystemController;
use App\Modules\Admin\Http\Controllers\TicketController;
use App\Modules\Admin\Http\Controllers\TranslationController;
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
        Route::put('restaurants/{restaurant}/domain', [RestaurantController::class, 'domain'])->name('restaurants.domain');
        Route::post('restaurants/{restaurant}/domain/verify', [RestaurantController::class, 'verifyDomain'])->name('restaurants.domain.verify');
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

        Route::get('system', [SystemController::class, 'index'])->name('system.index');
        Route::post('system/tool/{tool}', [SystemController::class, 'tool'])->middleware('throttle:20,1')->name('system.tool');
        Route::put('system/cron', [SystemController::class, 'cron'])->name('system.cron');
        Route::post('system/clear/{action}', [SystemController::class, 'clear'])->name('system.clear');
        Route::get('system/logs', [LogController::class, 'index'])->name('system.logs');
        Route::delete('system/logs', [LogController::class, 'clear'])->name('system.logs.clear');
        Route::get('system/backups', [BackupController::class, 'index'])->name('system.backups');
        Route::post('system/backups', [BackupController::class, 'store'])->name('system.backups.store');
        Route::get('system/backups/{name}', [BackupController::class, 'download'])->name('system.backups.download');
        Route::delete('system/backups/{name}', [BackupController::class, 'destroy'])->name('system.backups.destroy');

        Route::get('languages', [LanguageController::class, 'index'])->name('languages.index');
        Route::post('languages', [LanguageController::class, 'store'])->name('languages.store');
        Route::put('languages/{language}', [LanguageController::class, 'update'])->name('languages.update');
        Route::post('languages/{language}/default', [LanguageController::class, 'makeDefault'])->name('languages.default');
        Route::get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');
        Route::post('currencies', [CurrencyController::class, 'store'])->name('currencies.store');
        Route::put('currencies/{currency}', [CurrencyController::class, 'update'])->name('currencies.update');
        Route::get('translations', [TranslationController::class, 'index'])->name('translations.index');
        Route::put('translations', [TranslationController::class, 'update'])->name('translations.update');
        Route::delete('translations', [TranslationController::class, 'reset'])->name('translations.reset');

        Route::resource('coupons', CouponController::class)->except('show');

        Route::get('settings/billing', [BillingSettingsController::class, 'edit'])->name('settings.billing');
        Route::put('settings/billing', [BillingSettingsController::class, 'update'])->name('settings.billing.update');

        Route::get('settings/{section}', [SettingsController::class, 'edit'])->name('settings.section')
            ->where('section', 'general|seo|security|auth|mail|domains|ai|realtime|storage');
        Route::put('settings/{section}', [SettingsController::class, 'update'])->name('settings.section.update')
            ->where('section', 'general|seo|security|auth|mail|domains|ai|realtime|storage');
        Route::post('settings/ai/test', [SettingsController::class, 'testAi'])->middleware('throttle:5,1')->name('settings.ai.test');
        Route::post('settings/mail/test', [SettingsController::class, 'testMail'])->middleware('throttle:5,1')->name('settings.mail.test');

        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->whereNumber('ticket')->name('tickets.show');
        Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply'])->whereNumber('ticket')->name('tickets.reply');
        Route::post('tickets/{ticket}/close', [TicketController::class, 'close'])->whereNumber('ticket')->name('tickets.close');
        Route::post('tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->whereNumber('ticket')->name('tickets.reopen');
        Route::resource('announcements', AnnouncementController::class)->except('show');

        Route::get('landing', [LandingPageController::class, 'edit'])->name('landing.edit');
        Route::put('landing', [LandingPageController::class, 'update'])->name('landing.update');
        Route::resource('pages', PageController::class)->except('show');
        Route::resource('posts', BlogPostController::class)->except('show');

        Route::get('email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::get('email-templates/{key}', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
        Route::put('email-templates/{key}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
        Route::delete('email-templates/{key}', [EmailTemplateController::class, 'reset'])->name('email-templates.reset');
        Route::post('email-templates/{key}/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');
        Route::post('email-templates/{key}/test', [EmailTemplateController::class, 'test'])->middleware('throttle:5,1')->name('email-templates.test');

        Route::get('settings/guest-payments', [GuestPaymentsController::class, 'edit'])->name('settings.guest-payments');
        Route::put('settings/guest-payments', [GuestPaymentsController::class, 'update'])->name('settings.guest-payments.update');
        Route::get('settings/payments', [PaymentSettingsController::class, 'edit'])->name('settings.payments');
        Route::put('settings/payments/{gateway}', [PaymentSettingsController::class, 'update'])->name('settings.payments.update');
    });

// External cron services call this every minute (the secret is in the address; a wrong one answers 404).
Route::get('cron/{token}', CronController::class)->middleware('throttle:30,1')->name('cron.run');
