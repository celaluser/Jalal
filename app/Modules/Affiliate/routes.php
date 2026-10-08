<?php

use App\Modules\Affiliate\Http\Controllers\ReferralController;
use App\Modules\Core\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:billing.manage'])->get('referrals', [ReferralController::class, 'index'])->name('referrals.index');
