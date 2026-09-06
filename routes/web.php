<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FineFrontController;
use App\Http\Controllers\LoanFrontController;
use App\Http\Controllers\ProductFrontController;
use App\Http\Controllers\CategoryFrontController;
use App\Http\Controllers\BrandFrontController;
use App\Http\Controllers\ReturnProductFrontController;

Route::get('/', [ProductFrontController::class, 'index'])->name('home');

Route::middleware('auth')->controller(PaymentProofController::class)->group(function () {
    Route::get('payment-proofs/loans/{loan}', 'loan')->name('payment-proofs.loans.show');
    Route::get('payment-proofs/fines/{fine}', 'fine')->name('payment-proofs.fines.show');
});

Route::controller(DashboardController::class)->middleware(['auth', 'role:admin|operator|accounting|member'])->group(function(){
    Route::get('dashboard', 'index')->name('dashboard');
});

Route::controller(ProductFrontController::class)->group(function(){
    Route::get('products', 'index')->name('front.products.index');
    Route::get('products/{product:slug}', 'show')->name('front.products.show');
});

Route::controller(CategoryFrontController::class)->group(function(){
    Route::get('categories', 'index')->name('front.categories.index');
    Route::get('categories/{category:slug}', 'show')->name('front.categories.show');
});

Route::get('categories/{category:slug}/brands/{brand:slug}', [BrandFrontController::class, 'show'])
    // Category membership is checked through the brand's products in the controller.
    ->withoutScopedBindings()
    ->name('front.brands.show');

Route::controller(PaymentController::class)->group(function(){
    Route::get('payments/success', 'success')
        ->middleware(['auth', 'role:member'])->name('payments.success');
    Route::post('payments/{fine}/upload-proof', 'uploadProof')
        ->middleware(['auth', 'role:member'])->name('payments.upload-proof');
    Route::patch('payments/{fine}/approve', 'approve')
        ->middleware(['auth', 'role:admin|accounting'])->name('payments.approve');
    Route::patch('payments/{fine}/reject', 'reject')
        ->middleware(['auth', 'role:admin|accounting'])->name('payments.reject');
});

Route::controller(LoanFrontController::class)->middleware(['auth', 'role:member'])->group(function(){
    Route::get('loans', 'index')->name('front.loans.index');
    Route::get('loans/{loan:loan_code}/detail', 'show')->name('front.loans.show');
    Route::post('loans/{product:slug}/create', 'store')->name('front.loans.store');
    Route::get('loans/{loan}/checkout', 'checkout')->name('front.loans.checkout');
    Route::post('loans/{loan:loan_code}/payment', 'payment')
            ->name('front.loans.payment');
});

Route::controller(ReturnProductFrontController::class)->middleware(['auth', 'role:member'])->group(function(){
    Route::get('return-products', 'index')->name('front.return-products.index');
    Route::get('return-products/{returnProduct:return_product_code}/detail', 'show')->name('front.return-products.show');
    Route::post('return-products/{product:slug}/create/{loan:loan_code}', 'store')->name('front.return-products.store');
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('fines', FineFrontController::class)
    ->middleware(['auth', 'role:member'])
    ->name('front.fines.index');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
