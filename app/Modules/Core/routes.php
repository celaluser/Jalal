<?php

use App\Modules\Core\Http\Controllers\PublicFileController;
use Illuminate\Support\Facades\Route;

// Fallback for hosts where public/storage cannot be linked (see PublicFileController).
Route::get('storage/{path}', PublicFileController::class)->where('path', '.*')->name('storage.fallback');
