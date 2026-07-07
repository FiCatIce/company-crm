<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::resource('customers', CustomerController::class)->except(['show']);
    Route::resource('products', ProductController::class)->except(['show']);
});

require __DIR__.'/settings.php';
