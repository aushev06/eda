<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\Customer\AccountController;
use App\Http\Controllers\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Customer\NotificationsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\TableController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('home');
Route::get('/checkout', [CatalogController::class, 'checkout'])->name('checkout');
Route::get('/loyalty', [CatalogController::class, 'loyalty'])->name('loyalty');

Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
Route::get('/orders/{number}/thanks', [OrderController::class, 'thanks'])
    ->where('number', 'A-[A-Z0-9]+')
    ->name('orders.thanks');

Route::post('/promo-codes/validate', [PromoController::class, 'validateCode'])->name('promo.validate');

// Table QR entry
Route::get('/t/{token}', [TableController::class, 'enter'])
    ->where('token', '[a-z0-9]+')
    ->name('table.enter');
Route::post('/t/leave', [TableController::class, 'leave'])->name('table.leave');

// Customer auth (phone + OTP)
Route::get('/account/login', [CustomerAuthController::class, 'login'])->name('customer.login');
Route::post('/account/login/start', [CustomerAuthController::class, 'start'])->name('customer.login.start');
Route::get('/account/login/verify', [CustomerAuthController::class, 'verify'])->name('customer.verify');
Route::post('/account/login/verify', [CustomerAuthController::class, 'confirm'])->name('customer.verify.confirm');
Route::post('/account/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');

// Customer cabinet
Route::middleware('auth:customer')->prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::patch('/', [AccountController::class, 'updateProfile'])->name('update');
    Route::get('/orders', [AccountController::class, 'orders'])->name('orders');
    Route::get('/bonuses', [AccountController::class, 'bonuses'])->name('bonuses');
    Route::get('/addresses', [AccountController::class, 'addresses'])->name('addresses');
    Route::post('/addresses', [AccountController::class, 'storeAddress'])->name('addresses.store');
    Route::delete('/addresses/{address}', [AccountController::class, 'destroyAddress'])->name('addresses.destroy');

    Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications');
    Route::post('/notifications/read-all', [NotificationsController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationsController::class, 'markRead'])->name('notifications.read');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
