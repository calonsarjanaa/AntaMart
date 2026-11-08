<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\BrowserCheckoutController;
use App\Http\Controllers\Storefront\BrowserGuestOrderTrackingController;
use App\Http\Controllers\Storefront\BrowserGuestCancellationController;
use App\Http\Controllers\Storefront\CustomerAuthController;
use App\Http\Controllers\Storefront\CustomerOrderController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [StorefrontController::class, 'index'])->name('storefront.home');
Route::get('/products/{slug}', [StorefrontController::class, 'show'])->name('storefront.products.show');
Route::get('/cart', [CartController::class, 'index'])->name('storefront.cart.index');
Route::post('/cart/items', [CartController::class, 'store'])->name('storefront.cart.items.store');
Route::patch('/cart/items/{variantId}', [CartController::class, 'update'])->name('storefront.cart.items.update');
Route::delete('/cart/items/{variantId}', [CartController::class, 'destroy'])->name('storefront.cart.items.destroy');
Route::get('/checkout', [BrowserCheckoutController::class, 'create'])->name('storefront.checkout.create');
Route::post('/checkout', [BrowserCheckoutController::class, 'store'])->name('storefront.checkout.store');
Route::post('/checkout/payment/retry', [BrowserCheckoutController::class, 'retryPayment'])->name('storefront.checkout.payment.retry');
Route::get('/checkout/result/{orderId}', [BrowserCheckoutController::class, 'result'])->middleware('signed')->name('storefront.checkout.result');
Route::post('/checkout/result/{orderId}/retry', [BrowserCheckoutController::class, 'retryResultPayment'])->middleware('signed')->name('storefront.checkout.result.retry');
Route::get('/orders/track', [BrowserGuestOrderTrackingController::class, 'index'])->name('storefront.orders.track');
Route::post('/orders/track', [BrowserGuestOrderTrackingController::class, 'store'])->middleware('throttle:10,1')->name('storefront.orders.track.lookup');
Route::post('/orders/{id}/cancellation-requests', [BrowserGuestCancellationController::class, 'store'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('storefront.orders.cancellation.store');
Route::post('/orders/{id}/cancellation-requests/{requestId}/withdraw', [BrowserGuestCancellationController::class, 'withdraw'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('storefront.orders.cancellation.withdraw');

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:customer-login')->name('storefront.auth.login');
    Route::get('/register', [CustomerAuthController::class, 'showRegistration'])->name('register');
    Route::post('/register', [CustomerAuthController::class, 'register'])->middleware('throttle:customer-register')->name('storefront.auth.register');
});

Route::middleware(['auth:web', 'role:customer'])->prefix('/account')->name('storefront.account.')->group(function () {
    Route::get('/', [CustomerAuthController::class, 'account'])->name('index');
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');
    Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{orderId}', [CustomerOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{orderId}/cancellation-requests', [CustomerOrderController::class, 'requestCancellation'])->name('orders.cancellation.store');
    Route::post('/orders/{orderId}/cancellation-requests/{requestId}/withdraw', [CustomerOrderController::class, 'withdrawCancellation'])->name('orders.cancellation.withdraw');
});

Route::view('/owner', 'owner.index')
    ->middleware('role:store_owner')
    ->name('owner.dashboard');
