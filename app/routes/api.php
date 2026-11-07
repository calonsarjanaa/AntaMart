<?php

use App\Http\Controllers\Storefront\CheckoutController;
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
