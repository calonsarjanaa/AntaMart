<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Storefront\StorefrontController;

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

Route::view('/owner', 'owner.index')
    ->middleware('role:store_owner')
    ->name('owner.dashboard');
