<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PreOrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderDetailsController;
use App\Http\Controllers\NotificationController;
use App\Services\NotificationService;
use App\Http\Controllers\CropController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\FarmController;
use App\Http\Controllers\MarketSupplyController;
use App\Http\Controllers\RoleRequestController;


Route::prefix('auth')->group(function () {
    Route::post('/signup', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('get-products', [ProductController::class, 'index']);
    Route::get('/products', [ProductController::class, 'productNames']);
    Route::get('/market-supplies/listing',[MarketSupplyController::class,'index']);
    Route::get('/market-trend/listing',[PreOrderController::class,'trendingProducts']);
});

Route::middleware('auth:sanctum')->group(function () {
    // user info
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // role upgrade
    Route::post('auth/upgrade-to-farmer', [AuthController::class, 'upgradeToFarmer']);
    Route::post('auth/upgrade-to-vendor', [AuthController::class, 'upgradeToVendor']);
    Route::post('auth/update-profile', [AuthController::class, 'updateProfile']);
    Route::get('/showProfile/{id}', [AuthController::class, 'showProfile']);

    // role request 
    Route::post('/role-request', [RoleRequestController::class, 'store']);
    Route::get('/role-requests', [RoleRequestController::class, 'index']);
    Route::put('/role-request/{id}', [RoleRequestController::class, 'update']);

    // notification farmer and vendor
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']); 
        Route::get('/unread', [NotificationController::class, 'unreadCount']); 
        Route::put('{id}/mark-read', [NotificationController::class, 'markRead']); 
    });
    Route::get('pre-order/listing',[PreOrderController::class,'listPreOrders']);
    //Route::get('/get/pre-orders', [PreOrderController::class, 'listPreOrders']);
    Route::get('/filter/order-details', [OrderDetailsController::class, 'filterOrderDetails']);


    // vendor-only routes
    Route::middleware('role:vendor')->group(function () {

        Route::get('pre-orders/user/{user_id}', [PreOrderController::class, 'getByUser']);
        Route::post('pre-orders',[PreOrderController::class, 'store']);
        Route::put('pre-orders/{id}/status', [PreOrderController::class, 'updatePreOrderStatus']);
        Route::put('order-details/{id}/offer-status', [OrderDetailsController::class, 'confirmOffer']);
        Route::post('update/vendor-profile', [VendorController::class, 'updateVendorProfile']);    
        Route::get('order-details/listing',[OrderDetailsController::class, 'index']);
        Route::delete('delete/pre-orders/{id}', [PreOrderController::class, 'destroy']);
        Route::put('update/pre-orders/{id}', [PreOrderController::class, 'update']);
        Route::get('pre-orders/list-item', [PreOrderController::class, 'index']);
        Route::get('/market-supplies/listing',[MarketSupplyController::class,'index']);
        Route::get('/market-supplies/{id}', [MarketSupplyController::class, 'show']);
        Route::post('/pre-orders/from-surplus', [PreOrderController::class, 'storeFromSurplus']);

    });

    // farmer-only routes
    Route::middleware('role:farmer')->group(function () {
        Route::post('order-details/{preOrderId}', [OrderDetailsController::class, 'store']);
        Route::post('create-crop', [CropController::class, 'createCrop']);
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{id}', [ProductController::class, 'update']);
        Route::delete('products/{id}', [ProductController::class, 'destroy']);
        Route::post('update/farm-profile', [FarmController::class, 'updateFarmProfile']);
 
    });

    // admin role
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/role-requests', [RoleRequestController::class, 'index']);
        Route::put('/admin/role-requests/{id}/approve', [RoleRequestController::class, 'update']);
        Route::patch('update-product/{id}', [ProductController::class, 'update']);
        Route::post('update-product-image/{id}', [ProductController::class, 'updateWithFile']);
        Route::delete('delete-product/{id}', [ProductController::class, 'destroy']);
    });

    // Firebase
    //Route::post('/send-fcm', [FirebaseController::class, 'testFCM']);
});

