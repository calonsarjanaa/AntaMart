<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\BrowserCheckoutController;
use App\Http\Controllers\Storefront\BrowserGuestOrderTrackingController;
use App\Http\Controllers\Storefront\BrowserGuestCancellationController;
use App\Http\Controllers\Storefront\CustomerAuthController;
use App\Http\Controllers\Storefront\CustomerOrderController;
use App\Http\Controllers\Storefront\AgeVerificationController;
use App\Http\Controllers\Admin\OwnerAuthController;
use App\Http\Controllers\Admin\OwnerOrderController;
use App\Http\Controllers\Admin\OwnerCancellationController;
use App\Http\Controllers\Admin\OwnerRefundController;
use App\Http\Controllers\Admin\OwnerShipmentController;
use App\Http\Controllers\Admin\OwnerProductController;

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
Route::get('/categories/{category}/verify-age', [AgeVerificationController::class, 'show'])->name('storefront.age-verification.show');
Route::post('/categories/{category}/verify-age', [AgeVerificationController::class, 'verify'])->name('storefront.age-verification.verify');
Route::get('/products/{slug}', [StorefrontController::class, 'show'])->name('storefront.products.show');
Route::get('/cart', [CartController::class, 'index'])->name('storefront.cart.index');
Route::post('/cart/items', [CartController::class, 'store'])->name('storefront.cart.items.store');
Route::patch('/cart/items/{variantId}', [CartController::class, 'update'])->name('storefront.cart.items.update');
Route::delete('/cart/items/{variantId}', [CartController::class, 'destroy'])->name('storefront.cart.items.destroy');
Route::get('/checkout', [BrowserCheckoutController::class, 'create'])->name('storefront.checkout.create');
Route::post('/checkout', [BrowserCheckoutController::class, 'store'])->name('storefront.checkout.store');
Route::post('/checkout/payment/retry', [BrowserCheckoutController::class, 'retryPayment'])->name('storefront.checkout.payment.retry');
Route::post('/checkout/payment/switch-cod', [BrowserCheckoutController::class, 'switchToCod'])->name('storefront.checkout.payment.switch-cod');
Route::post('/checkout/payment/cancel', [BrowserCheckoutController::class, 'cancelPending'])->name('storefront.checkout.payment.cancel');
Route::post('/checkout/payment/dev-simulate', [BrowserCheckoutController::class, 'devSimulatePayment'])->name('storefront.checkout.payment.dev-simulate');
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

Route::middleware('guest:web')->prefix('/owner')->name('owner.')->group(function () {
    Route::get('/login', [OwnerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [OwnerAuthController::class, 'login'])->middleware('throttle:owner-login')->name('auth.login');
});

Route::middleware(['auth:web', 'role:store_owner'])->prefix('/owner')->name('owner.')->group(function () {
    Route::view('/', 'owner.index')->name('dashboard');
    Route::get('/products', [OwnerProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [OwnerProductController::class, 'create'])->name('products.create');
    Route::post('/products', [OwnerProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [OwnerProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [OwnerProductController::class, 'update'])->name('products.update');
    Route::post('/products/{product}/variants', [OwnerProductController::class, 'storeVariant'])->name('products.variants.store');
    Route::put('/products/{product}/variants/{variant}', [OwnerProductController::class, 'updateVariant'])->name('products.variants.update');
    Route::get('/cancellations', [OwnerCancellationController::class, 'index'])->name('cancellations.index');
    Route::get('/orders', [OwnerOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{orderId}', [OwnerOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{orderId}/cancellation-requests/{requestId}/review', [OwnerCancellationController::class, 'review'])->name('orders.cancellations.review');
    Route::post('/orders/{orderId}/cancel', [OwnerCancellationController::class, 'cancelOrder'])->name('orders.cancel');
    Route::post('/orders/{orderId}/refund/mark-refunded', [OwnerRefundController::class, 'markRefunded'])->name('orders.refund.mark-refunded');
    Route::post('/orders/{orderId}/shipment', [OwnerShipmentController::class, 'store'])->name('orders.shipment.store');
    Route::post('/orders/{orderId}/delivery/confirm', [OwnerShipmentController::class, 'confirmDelivery'])->name('orders.delivery.confirm');
    Route::post('/logout', [OwnerAuthController::class, 'logout'])->name('logout');
});
