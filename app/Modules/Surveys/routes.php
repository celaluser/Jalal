<?php

use App\Modules\Core\Http\Middleware\SetLocale;
use App\Modules\Surveys\Http\Controllers\GuestSurveyController;
use App\Modules\Surveys\Http\Controllers\SurveyController;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

$guest = function () {
    Route::get('survey/{slug}', [GuestSurveyController::class, 'show'])->where('slug', '[a-z0-9-]{1,80}')->name('survey');
    Route::post('survey/{slug}', [GuestSurveyController::class, 'submit'])->where('slug', '[a-z0-9-]{1,80}')->middleware('throttle:10,1')->name('survey.submit');
};

Route::middleware(['web', SetLocale::class, ResolveTenant::class])->prefix(config('tenancy.path_prefix').'/{restaurant}')->name('storefront.')->where(['restaurant' => '[A-Za-z0-9-]+'])->group($guest);
Route::middleware(['web', SetLocale::class, ResolveTenant::class])->name('storefront.host.')->group($guest);

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:marketing.manage'])->prefix('surveys')->name('surveys.')->group(function () {
    Route::get('/', [SurveyController::class, 'index'])->name('index');
    Route::get('create', [SurveyController::class, 'create'])->name('create');
    Route::post('/', [SurveyController::class, 'store'])->middleware('throttle:30,1')->name('store');
    Route::get('{survey}/edit', [SurveyController::class, 'edit'])->whereNumber('survey')->name('edit');
    Route::put('{survey}', [SurveyController::class, 'update'])->whereNumber('survey')->name('update');
    Route::delete('{survey}', [SurveyController::class, 'destroy'])->whereNumber('survey')->name('destroy');
    Route::get('{survey}/results', [SurveyController::class, 'results'])->whereNumber('survey')->name('results');
    Route::get('{survey}/export', [SurveyController::class, 'export'])->whereNumber('survey')->middleware('throttle:10,1')->name('export');
});
