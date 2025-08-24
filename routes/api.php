<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PreOrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderDetailsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CropController;
use App\Http\Controllers\FirebaseController;

// -------------------- PUBLIC ROUTES --------------------
Route::prefix('auth')->group(function () {
    Route::post('/signup', [AuthController::class, 'store']);   // create account
    Route::post('/login', [AuthController::class, 'login']);    // login
});

// -------------------- AUTHENTICATED ROUTES --------------------
Route::middleware('auth:sanctum')->group(function () {

    // user info
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // upgrade roles
    Route::post('auth/upgrade-to-farmer', [AuthController::class, 'upgradeToFarmer']);
    Route::post('auth/upgrade-to-vendor', [AuthController::class, 'upgradeToVendor']);

    // notifications
    Route::apiResource('notifications', NotificationController::class);
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead']);

    // -------------------- VENDOR ROUTES --------------------
    Route::middleware('role:vendor')->group(function () {
        Route::apiResource('pre-orders', PreOrderController::class);
        Route::get('pre-orders/user/{user_id}', [PreOrderController::class, 'getByUser']);
        Route::put('pre-orders/{id}/status', [PreOrderController::class, 'updatePreOrderStatus']);  
        Route::put('order-details/{id}/confirm', [OrderDetailsController::class, 'confirmOffer']);  
    });

    // -------------------- FARMER ROUTES --------------------
    Route::middleware('role:farmer')->group(function () {
        Route::apiResource('products', ProductController::class);
        Route::apiResource('order-details', OrderDetailsController::class);
        Route::post('create-crop', [CropController::class, 'createCrop']);
    });

    // -------------------- FIREBASE --------------------
    Route::post('/send-fcm', [FirebaseController::class, 'testFCM']);
});
