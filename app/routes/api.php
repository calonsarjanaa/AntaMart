<?php

use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\RetryPaymentSessionController;
use App\Http\Controllers\Webhook\XenditController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Storefront Checkout (Guest & Auth)
Route::post('/checkout', CheckoutController::class);

// Retry / Re-create payment session
Route::post('/orders/{id}/payment-session', RetryPaymentSessionController::class);

// Xendit Webhook Callback
Route::post('/webhooks/xendit', XenditController::class)->middleware('xendit.verify');
