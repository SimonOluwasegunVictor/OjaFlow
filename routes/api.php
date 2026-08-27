<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PaymentAccountController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('business.member')->group(function () {
        Route::get('/business', [BusinessController::class, 'show']);
        Route::get('/payment-accounts', [PaymentAccountController::class, 'index']);
        Route::put('/users/{userId}', [AuthController::class, 'update']);
        Route::delete('/users/{userId}', [AuthController::class, 'delete']);

        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/stock-movements', [ProductController::class, 'allMovements']);
        Route::get('/dashboard-report', [ReportController::class, 'dashboard']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{productId}', [ProductController::class, 'update']);
        Route::patch('/products/{productId}/archive', [ProductController::class, 'archive']);
        Route::patch('/products/{productId}/stock', [ProductController::class, 'adjustStock']);
        Route::get('/products/{productId}/movements', [ProductController::class, 'movements']);

        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::put('/customers/{customerId}', [CustomerController::class, 'update']);

        Route::get('/cart', [CartController::class, 'show']);
        Route::patch('/cart', [CartController::class, 'update']);
        Route::delete('/cart', [CartController::class, 'clear']);
        Route::post('/cart/items', [CartController::class, 'addItem']);
        Route::patch('/cart/items/{cartItemId}', [CartController::class, 'updateItem']);
        Route::delete('/cart/items/{cartItemId}', [CartController::class, 'removeItem']);

        Route::post('/sales', [SaleController::class, 'store']);

        Route::get('/debts', [DebtController::class, 'index']);
        Route::post('/debts/{saleId}/payments', [DebtController::class, 'recordPayment']);

        Route::middleware('business.admin')->group(function () {
            Route::put('/business', [BusinessController::class, 'update']);
            Route::post('/payment-accounts', [PaymentAccountController::class, 'store']);
            Route::put('/payment-accounts/{accountId}', [PaymentAccountController::class, 'update']);
            Route::delete('/payment-accounts/{accountId}', [PaymentAccountController::class, 'destroy']);
            Route::get('/branches', [BranchController::class, 'index']);
            Route::post('/branches', [BranchController::class, 'store']);
            Route::get('/branches/{branchId}', [BranchController::class, 'show']);
            Route::put('/branches/{branchId}', [BranchController::class, 'update']);
            Route::patch('/branches/{branchId}/status', [BranchController::class, 'updateStatus']);

            Route::get('/staff-permissions', [StaffController::class, 'permissions']);
            Route::get('/staff', [StaffController::class, 'index']);
            Route::post('/staff', [StaffController::class, 'store']);
            Route::get('/staff/{staffId}', [StaffController::class, 'show']);
            Route::put('/staff/{staffId}', [StaffController::class, 'update']);
            Route::patch('/staff/{staffId}/status', [StaffController::class, 'updateStatus']);
            Route::patch('/staff/{staffId}/password', [StaffController::class, 'resetPassword']);
            Route::patch('/staff/{staffId}/permissions', [StaffController::class, 'updatePermissions']);
            Route::delete('/staff/{staffId}', [StaffController::class, 'destroy']);
        });
    });
});
