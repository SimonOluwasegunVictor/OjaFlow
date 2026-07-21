<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StaffController;
// use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('business.member')->group(function () {
        Route::put('/users/{userId}', [AuthController::class, 'update']);
        Route::delete('/users/{userId}', [AuthController::class, 'delete']);

        Route::middleware('business.admin')->group(function () {
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
