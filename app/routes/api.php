<?php

use App\Http\Controllers\Admin\CancellationController as AdminCancellationController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Storefront\CancellationRequestController;
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

// Customer and guest order cancellation
Route::post('/orders/{id}/cancellation-requests', [CancellationRequestController::class, 'store']);
Route::post('/orders/{id}/cancellation-requests/{requestId}/withdraw', [CancellationRequestController::class, 'withdraw']);

// Store Owner cancellation review and immediate cancellation
Route::middleware(['auth:sanctum', 'role:store_owner'])->prefix('admin')->group(function () {
    Route::post('/orders/{id}/cancellation-requests/{requestId}/review', [AdminCancellationController::class, 'review']);
    Route::post('/orders/{id}/cancel', [AdminCancellationController::class, 'cancel']);
    Route::post('/orders/{id}/refund/mark-refunded', [RefundController::class, 'markRefunded']);
});

// Xendit Webhook Callback
Route::post('/webhooks/xendit', XenditController::class)->middleware('xendit.verify');
