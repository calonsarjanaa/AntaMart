<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\BrowserCheckoutController;

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

Route::view('/owner', 'owner.index')
    ->middleware('role:store_owner')
    ->name('owner.dashboard');
