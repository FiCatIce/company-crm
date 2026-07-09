<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::resource('customers', CustomerController::class);
    // Lightweight quick-changes from the Customer 360 header (single field each).
    Route::patch('customers/{customer}/status', [CustomerController::class, 'updateStatus'])->name('customers.status');
    Route::patch('customers/{customer}/owner', [CustomerController::class, 'updateOwner'])->name('customers.owner');
    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('resellers', ResellerController::class)->except(['show']);
    Route::resource('transactions', TransactionController::class)->except(['show']);

    // Interactions — shallow nested: create under a customer, edit/delete standalone.
    Route::post('customers/{customer}/interactions', [InteractionController::class, 'store'])->name('interactions.store');
    Route::put('interactions/{interaction}', [InteractionController::class, 'update'])->name('interactions.update');
    Route::delete('interactions/{interaction}', [InteractionController::class, 'destroy'])->name('interactions.destroy');
});

require __DIR__.'/settings.php';
