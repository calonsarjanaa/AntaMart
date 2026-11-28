<?php

use App\Http\Controllers\Admin\CancellationController as AdminCancellationController;
use App\Http\Controllers\Admin\OrderConfirmationController as AdminOrderConfirmationController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Storefront\CancellationRequestController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\GuestOrderTrackingController;
use App\Http\Controllers\Storefront\ProductCatalogController;
use App\Http\Controllers\Storefront\RegionController;
use App\Http\Controllers\Storefront\RetryPaymentSessionController;
use App\Http\Controllers\Storefront\ShippingRateController;
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
Route::get('/products', ProductCatalogController::class);
Route::get('/regions/provinces', [RegionController::class, 'provinces']);
Route::get('/regions/regencies/{provinceId}', [RegionController::class, 'regencies']);
Route::get('/regions/districts/{regencyId}', [RegionController::class, 'districts']);
Route::post('/shipping/rates', [ShippingRateController::class, 'rates']);
Route::get('/shipping/geocode', [ShippingRateController::class, 'geocode']);
Route::post('/checkout', CheckoutController::class);

// Retry / Re-create payment session
Route::post('/orders/{id}/payment-session', RetryPaymentSessionController::class);

// Guest order tracking
Route::get('/track', GuestOrderTrackingController::class)->middleware('throttle:10,1');

// Customer and guest order cancellation
Route::post('/orders/{id}/cancellation-requests', [CancellationRequestController::class, 'store'])->middleware('throttle:10,1');
Route::post('/orders/{id}/cancellation-requests/{requestId}/withdraw', [CancellationRequestController::class, 'withdraw'])->middleware('throttle:10,1');

// Store Owner cancellation review and immediate cancellation
Route::middleware(['auth:sanctum', 'role:store_owner'])->prefix('admin')->group(function () {
    Route::post('/orders/{id}/accept', [AdminOrderConfirmationController::class, 'accept']);
    Route::post('/orders/{id}/reject', [AdminOrderConfirmationController::class, 'reject']);
    Route::post('/orders/{id}/cancellation-requests/{requestId}/review', [AdminCancellationController::class, 'review']);
    Route::post('/orders/{id}/cancel', [AdminCancellationController::class, 'cancel']);
    Route::post('/orders/{id}/refund/mark-refunded', [RefundController::class, 'markRefunded']);
    Route::post('/orders/{id}/shipment', [ShipmentController::class, 'store']);
    Route::post('/orders/{id}/shipment/confirm-delivery', [ShipmentController::class, 'confirmDelivery']);
});

// Xendit Webhook Callback
Route::post('/webhooks/xendit', XenditController::class)->middleware('xendit.verify');
