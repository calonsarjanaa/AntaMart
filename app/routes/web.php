<?php

use Illuminate\Support\Facades\Route;

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

Route::view('/', 'storefront.home')->name('storefront.home');

Route::view('/owner', 'owner.index')
    ->middleware('role:store_owner')
    ->name('owner.dashboard');
