<?php

use App\Modules\Ai\Http\Controllers\AiController;
use App\Modules\Core\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:menu.manage'])->prefix('ai')->name('ai.')->group(function () {
    Route::get('import', [AiController::class, 'importPage'])->name('import');
    // Every call can cost money, so each person gets a modest rate.
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('describe', [AiController::class, 'describe'])->name('describe');
        Route::post('translate', [AiController::class, 'translate'])->name('translate');
        Route::post('tags', [AiController::class, 'tags'])->name('tags');
        Route::post('import/preview', [AiController::class, 'importPreview'])->name('import.preview');
    });
    Route::post('import/commit', [AiController::class, 'importCommit'])->name('import.commit');
});
