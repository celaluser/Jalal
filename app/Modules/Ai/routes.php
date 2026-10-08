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
        Route::post('import/photo', [AiController::class, 'importPhoto'])->name('import.photo');
        Route::post('import/pdf', [AiController::class, 'importPdf'])->name('import.pdf');
        Route::post('translate-all', [AiController::class, 'bulkTranslate'])->middleware('throttle:3,10')->name('translate-all');
    });
    Route::get('translate-all/status', [AiController::class, 'bulkStatus'])->name('translate-all.status');
    Route::post('import/commit', [AiController::class, 'importCommit'])->name('import.commit');
});

// Drafts and advice that belong to other parts of the panel, each with the permission of that part.
Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'throttle:20,1'])->prefix('ai')->name('ai.')->group(function () {
    Route::post('reviews/{review}/reply', [AiController::class, 'reviewReply'])->whereNumber('review')->middleware('permission:marketing.manage')->name('review-reply');
    Route::get('insights', [AiController::class, 'insights'])->middleware('permission:reports.view')->name('insights');
});
