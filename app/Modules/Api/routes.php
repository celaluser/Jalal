<?php

use App\Modules\Api\Http\Controllers\ApiController;
use App\Modules\Api\Http\Controllers\ApiSettingsController;
use App\Modules\Core\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// REST API v1. Token auth, no session or CSRF. A generous per-IP cap runs before authentication (against guessing),
// then a per-token cap.
Route::middleware(['throttle:300,1'])->prefix('api/v1')->name('api.')->group(function () {
    Route::middleware(['api.token', 'throttle:api-token'])->group(function () {
        Route::get('me', [ApiController::class, 'me'])->name('me');
        Route::get('menu', [ApiController::class, 'menu'])->middleware('api.token:menu:read')->name('menu');
        Route::patch('products/{product}', [ApiController::class, 'updateProduct'])->whereNumber('product')->middleware('api.token:menu:write')->name('products.update');
        Route::get('orders', [ApiController::class, 'orders'])->middleware('api.token:orders:read')->name('orders');
        Route::get('orders/{order}', [ApiController::class, 'order'])->whereNumber('order')->middleware('api.token:orders:read')->name('orders.show');
        Route::post('orders/{order}/status', [ApiController::class, 'orderStatus'])->whereNumber('order')->middleware('api.token:orders:write')->name('orders.status');
    });
});

Route::middleware(['web', SetLocale::class, 'auth', 'verified', 'tenant.user', 'permission:api.manage'])->prefix('integrations')->group(function () {
    Route::get('/', [ApiSettingsController::class, 'index'])->name('integrations.index');
    Route::post('tokens', [ApiSettingsController::class, 'createToken'])->middleware('throttle:20,1')->name('integrations.tokens.store');
    Route::delete('tokens/{token}', [ApiSettingsController::class, 'revokeToken'])->whereNumber('token')->name('integrations.tokens.destroy');
    Route::post('webhooks', [ApiSettingsController::class, 'createEndpoint'])->middleware('throttle:20,1')->name('integrations.webhooks.store');
    Route::post('webhooks/{endpoint}/toggle', [ApiSettingsController::class, 'toggleEndpoint'])->whereNumber('endpoint')->name('integrations.webhooks.toggle');
    Route::post('webhooks/{endpoint}/test', [ApiSettingsController::class, 'testEndpoint'])->middleware('throttle:10,1')->whereNumber('endpoint')->name('integrations.webhooks.test');
    Route::delete('webhooks/{endpoint}', [ApiSettingsController::class, 'deleteEndpoint'])->whereNumber('endpoint')->name('integrations.webhooks.destroy');
});
