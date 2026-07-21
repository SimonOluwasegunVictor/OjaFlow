<?php

use App\Http\Controllers\AuthController;
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
        Route::post('/staff', [AuthController::class, 'createStaff'])
            ->middleware('business.admin');

        Route::put('/users/{userId}', [AuthController::class, 'update']);
        Route::delete('/users/{userId}', [AuthController::class, 'delete']);
    });
});
