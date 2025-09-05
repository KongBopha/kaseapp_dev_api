<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PreOrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderDetailsController;
use App\Http\Controllers\NotificationController;
use App\Services\NotificationService;
use App\Http\Controllers\CropController;
use App\Http\Controllers\FirebaseController;

Route::prefix('auth')->group(function () {
    Route::post('/signup', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('get-products', [ProductController::class, 'index']);
    Route::get('get-products/byname', [ProductController::class, 'productNames']);
    Route::get('products/{id}', [ProductController::class, 'show']);
});

Route::middleware('auth:sanctum')->group(function () {
    // user info
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // role upgrade
    Route::post('auth/upgrade-to-farmer', [AuthController::class, 'upgradeToFarmer']);
    Route::post('auth/upgrade-to-vendor', [AuthController::class, 'upgradeToVendor']);
    // notification farmer and vendor
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']); 
        Route::get('/unread', [NotificationController::class, 'unreadCount']); 
        Route::post('/mark-read/{id}', [NotificationController::class, 'markRead']); 
    });

    // vendor-only routes
    Route::middleware('role:vendor')->group(function () {
        Route::apiResource('pre-orders', PreOrderController::class);
        Route::get('pre-orders/user/{user_id}', [PreOrderController::class, 'getByUser']);
        Route::put('pre-orders/{id}/status', [PreOrderController::class, 'updatePreOrderStatus']);
        Route::get('order-details/listing',[OrderDetailsController::class, 'index']);
        Route::put('order-details/{id}/confirm', [OrderDetailsController::class, 'confirmOffer']);

        //Route::post('auth/notifications/send-to-farmers', [NotificationController::class, 'sendToFarmers']);
    });

    // farmer-only routes
    Route::middleware('role:farmer')->group(function () {
        Route::post('order-details/{preOrderId}', [OrderDetailsController::class, 'store']);
        Route::post('create-crop', [CropController::class, 'createCrop']);
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{id}', [ProductController::class, 'update']);
        Route::delete('products/{id}', [ProductController::class, 'destroy']);
        Route::get('pre-order/listing',[PreOrderController::class,'index']);
        //Route::post('auth/notifications/send-to-vendor', [NotificationController::class, 'sendToVendor']);
    });

    // Firebase
    Route::post('/send-fcm', [FirebaseController::class, 'testFCM']);
});

