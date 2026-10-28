<?php

use App\Http\Controllers\Api\PosApiController;
use App\Http\Controllers\Api\PosAuthController;
use Illuminate\Support\Facades\Route;

// POS mobile app: token auth, the same staff gate as the web POS
Route::prefix('pos')->name('api.pos.')->group(function () {
    Route::post('/login', [PosAuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    Route::middleware(['auth:sanctum', 'can:pos'])->group(function () {
        Route::get('/me', [PosAuthController::class, 'me'])->name('me');
        Route::post('/logout', [PosAuthController::class, 'logout'])->name('logout');

        Route::get('/catalog', [PosApiController::class, 'catalog'])->name('catalog');
        Route::post('/quote', [PosApiController::class, 'quote'])->name('quote');
        Route::post('/orders', [PosApiController::class, 'store'])->name('orders.store');

        Route::get('/tables', [PosApiController::class, 'tables'])->name('tables');
        Route::post('/tables/{table}/check', [PosApiController::class, 'openCheck'])->name('tables.check');
        Route::get('/orders/{order}/check', [PosApiController::class, 'showCheck'])->name('orders.check');
        Route::post('/orders/{order}/items', [PosApiController::class, 'addItems'])->name('orders.items');
        Route::delete('/order-items/{item}', [PosApiController::class, 'removeItem'])->name('order-items.remove');
        Route::post('/orders/{order}/close', [PosApiController::class, 'close'])->name('orders.close');

        Route::get('/kds/{station}', [PosApiController::class, 'kds'])
            ->where('station', 'kitchen|bar')
            ->name('kds');
        Route::post('/orders/{order}/bump/{station}', [PosApiController::class, 'bump'])
            ->where('station', 'kitchen|bar')
            ->name('orders.bump');
    });
});
