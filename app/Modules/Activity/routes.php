<?php

use App\Modules\Activity\Http\Controllers\ActivityController;
use App\Modules\Core\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:activity.view'])->get('activity', [ActivityController::class, 'index'])->name('activity.index');
