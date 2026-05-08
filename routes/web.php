<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\MediaUploadController;
use App\Http\Controllers\Api\NotificationSubscriptionController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\ProductCatalogController;
use App\Http\Controllers\Api\ProductMappingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\EnsureActiveTenant;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    Route::middleware(['auth', EnsureActiveTenant::class])->group(function () {
        Route::get('/auth/session', [AuthController::class, 'session']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/portal', PortalController::class);
        Route::get('/workspace', PortalController::class);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{uuid}', [OrderController::class, 'show']);
        Route::post('/orders/imports/preview', [OrderController::class, 'importPreview']);
        Route::get('/products', [ProductCatalogController::class, 'index']);
        Route::post('/products/types', [ProductCatalogController::class, 'storeType']);
        Route::patch('/products/types/{productType}', [ProductCatalogController::class, 'updateType']);
        Route::delete('/products/types/{productType}', [ProductCatalogController::class, 'destroyType']);
        Route::post('/products/types/{productType}/variants', [ProductCatalogController::class, 'storeVariant']);
        Route::patch('/products/variants/{variant}', [ProductCatalogController::class, 'updateVariant']);
        Route::delete('/products/variants/{variant}', [ProductCatalogController::class, 'destroyVariant']);
        Route::post('/products/types/{productType}/options', [ProductCatalogController::class, 'storeOption']);
        Route::patch('/products/options/{option}', [ProductCatalogController::class, 'updateOption']);
        Route::delete('/products/options/{option}', [ProductCatalogController::class, 'destroyOption']);
        Route::get('/product-mappings', [ProductMappingController::class, 'index']);
        Route::post('/product-mappings', [ProductMappingController::class, 'store']);
        Route::post('/product-mappings/simulate', [ProductMappingController::class, 'simulate']);
        Route::post('/product-mappings/conflicts', [ProductMappingController::class, 'conflicts']);
        Route::patch('/product-mappings/{mapping}', [ProductMappingController::class, 'update']);
        Route::delete('/product-mappings/{mapping}', [ProductMappingController::class, 'destroy']);
        Route::post('/uploads', [MediaUploadController::class, 'store']);
        Route::post('/issues/{type}', [IssueController::class, 'store']);
        Route::patch('/notifications/subscriptions/{subscription}', [NotificationSubscriptionController::class, 'update']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users/invites', [UserController::class, 'invite']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
    });
});

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
